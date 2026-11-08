<?php
namespace Modules\ZabbixImportExport\Lib;

/** CMDB-style namespace/capability detection, preserving the exact version when available. */
final class ZabbixVersion
{
    private static $version;

    public static function detect(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }
        if (
            defined("ZABBIX_VERSION") &&
            is_string(ZABBIX_VERSION) &&
            preg_match('/^\d+\.\d+(?:\.\d+)?(?:[a-zA-Z0-9.+-]*)$/D', ZABBIX_VERSION)
        ) {
            return self::$version = ZABBIX_VERSION;
        }
        if (class_exists("Zabbix\\Core\\CModule")) {
            return self::$version = "7.0.0";
        }
        if (class_exists("Core\\CModule")) {
            return self::$version = "6.0.0";
        }
        throw new \RuntimeException("Cannot determine the Zabbix frontend version.");
    }
    public static function get(): string
    {
        return self::detect();
    }
    public static function atLeast(string $version): bool
    {
        return version_compare(self::get(), $version, ">=");
    }
    public static function modern(): bool
    {
        return self::atLeast("7.0");
    }
    public static function isVersion6(): bool
    {
        return self::atLeast("6.0") && !self::atLeast("7.0");
    }
    public static function isVersion7(): bool
    {
        return self::atLeast("7.0") && !self::atLeast("8.0");
    }
    public static function groups(): string
    {
        return self::atLeast("6.2") ? "selectHostGroups" : "selectGroups";
    }
    public static function manifestVersion(string $version): int
    {
        return version_compare($version, "6.4", ">=") ? 2 : 1;
    }
    public static function getModuleBaseClass(): string
    {
        foreach (["Zabbix\\Core\\CModule", "Core\\CModule"] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        throw new \RuntimeException("The native Zabbix module base class is unavailable.");
    }
    public static function getDisableCsrfMethod($controller): string
    {
        foreach (["disableCsrfValidation", "disableSIDvalidation"] as $method) {
            if (method_exists($controller, $method)) {
                return $method;
            }
        }
        throw new \RuntimeException("The native Zabbix request validation API is unavailable.");
    }
    public static function csrfName(): string
    {
        if (class_exists("CCsrfTokenHelper") && defined("CSRF_TOKEN_NAME")) {
            return CSRF_TOKEN_NAME;
        }
        if (self::isVersion6()) {
            return "sid";
        }
        throw new \RuntimeException("The native Zabbix CSRF helper is unavailable.");
    }
    public static function csrfToken(string $action): string
    {
        if (self::csrfName() !== "sid") {
            return \CCsrfTokenHelper::get($action);
        }
        $session = \CSessionHelper::getId();
        if (!is_string($session) || strlen($session) < 32) {
            throw new \RuntimeException("Invalid Zabbix session.");
        }
        return substr($session, 16, 16);
    }
    public static function moduleManager(): \CModuleManager
    {
        // 6.0 manager uses a modules directory and a three-argument addModule().
        $method = new \ReflectionMethod("CModuleManager", "addModule");
        return new \CModuleManager(
            \APP::getRootDir() . ($method->getNumberOfParameters() >= 4 ? "" : "/modules")
        );
    }
    public static function moduleManifest(string $path, string $id): ?array
    {
        $manager = self::moduleManager();
        $method = new \ReflectionMethod($manager, "addModule");
        return $method->getNumberOfParameters() >= 4
            ? $manager->addModule($path, null, $id)
            : $manager->addModule(substr($path, 8), $id);
    }
    public static function moduleStoragePath(string $path): string
    {
        return (new \ReflectionMethod("CModuleManager", "addModule"))->getNumberOfParameters() >= 4
            ? $path
            : substr($path, 8);
    }
    public static function portableModulePath(string $path): string
    {
        return strpos($path, "modules/") === 0 ? $path : "modules/" . $path;
    }
}
