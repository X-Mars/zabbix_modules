<?php
namespace Modules\ZabbixImportExport\Lib;
final class Security
{
    public static function isSuperAdmin(): bool
    {
        return (\CWebUser::$data["type"] ?? 0) >=
            (defined("USER_TYPE_SUPER_ADMIN") ? USER_TYPE_SUPER_ADMIN : 3);
    }
    public static function executionRisks(array $item): array
    {
        $risks = [];
        if (in_array((int) ($item["type"] ?? -1), [10, 11, 13, 14, 19, 21, 22], true)) {
            $risks[] = "Active or executable monitoring item";
        }
        if (preg_match('/^system\.run(?:\[|$)/i', (string) ($item["key_"] ?? ""))) {
            $risks[] = "Agent system.run command";
        }
        foreach ($item["preprocessing"] ?? [] as $step) {
            if (is_array($step) && (int) ($step["type"] ?? -1) === 21) {
                $risks[] = "JavaScript preprocessing";
            }
        }
        return array_values(array_unique($risks));
    }
    public static function assertExecutableApproval(array $item, array $options): void
    {
        if (
            self::executionRisks($item) &&
            (empty($options["allow_executable"]) || !self::isSuperAdmin())
        ) {
            throw new \RuntimeException(
                "Executable or active monitoring configuration requires explicit Super admin approval."
            );
        }
    }
    public static function moduleConfigHasCode(array $config): bool
    {
        foreach ($config as $key => $value) {
            if (
                preg_match(
                    '/(?:^|[_-])(?:php|script|code|command|exec|callback|include|require)(?:$|[_-])/i',
                    (string) $key
                ) &&
                $value !== "" &&
                $value !== []
            ) {
                return true;
            }
            if (is_string($value) && preg_match("/<\?(?:php\b|=)/i", $value)) {
                return true;
            }
            if (is_array($value) && self::moduleConfigHasCode($value)) {
                return true;
            }
        }
        return false;
    }
    public static function assertModuleConfigApproval(array $config, array $options): void
    {
        if (
            self::moduleConfigHasCode($config) &&
            (empty($options["allow_executable"]) || !self::isSuperAdmin())
        ) {
            throw new \RuntimeException(
                "Code-related module settings require explicit Super admin approval."
            );
        }
    }
    public static function sensitive(string $key): bool
    {
        return (bool) preg_match(
            "/pass(word|wd|phrase)?|token|secret|psk|private|authorization|cookie|community|credential|api.?key/i",
            $key
        );
    }
    public static function redact(array $data, array &$removed, string $path = ""): array
    {
        foreach ($data as $key => $value) {
            $p = $path === "" ? (string) $key : $path . "." . $key;
            if (
                self::sensitive((string) $key) &&
                $value !== "" &&
                $value !== null &&
                $value !== []
            ) {
                $removed[] = $p;
                unset($data[$key]);
                continue;
            }
            // HTTP headers / name-value parameters can carry secrets in their values.
            if (
                is_array($value) &&
                (isset($value["name"]) || isset($value["tag"]) || isset($value["macro"])) &&
                (self::sensitive((string) ($value["name"] ?? ($value["tag"] ?? $value["macro"]))) ||
                    (isset($value["macro"]) &&
                        in_array(
                            (string) ($value["type"] ?? ""),
                            ["1", "2", "SECRET_TEXT", "VAULT"],
                            true
                        )))
            ) {
                $removed[] = $p;
                unset($data[$key]);
                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::redact($value, $removed, $p);
            } elseif (
                is_string($value) &&
                (preg_match("~https?://[^/@\s:]+:[^/@\s]+@~i", $value) ||
                    preg_match(
                        '/(?:password|api[_-]?key|token|secret|authorization)[\s"\x27]*(?:[:=]|Bearer)/i',
                        $value
                    ))
            ) {
                $removed[] = $p;
                unset($data[$key]);
            }
        }
        return $data;
    }
    public static function modulePath(string $path): string
    {
        if (!preg_match('~^modules/[A-Za-z0-9_-]+$~D', $path)) {
            throw new \RuntimeException("Unsafe module path.");
        }
        $root = realpath(\APP::getRootDir() . "/modules");
        $dir = realpath(\APP::getRootDir() . "/" . $path);
        if (
            !$root ||
            !$dir ||
            strpos($dir, $root . DIRECTORY_SEPARATOR) !== 0 ||
            is_link(\APP::getRootDir() . "/" . $path) ||
            !is_file($dir . "/manifest.json")
        ) {
            throw new \RuntimeException("Deploy module files first.");
        }
        $manifest = realpath($dir . "/manifest.json");
        if (
            is_link($dir . "/manifest.json") ||
            !$manifest ||
            dirname($manifest) !== $dir ||
            filesize($manifest) > 262144
        ) {
            throw new \RuntimeException("Unsafe module manifest.");
        }
        return $dir;
    }
}
