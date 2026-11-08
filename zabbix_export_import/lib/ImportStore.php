<?php
namespace Modules\ZabbixImportExport\Lib;
/** Temporary previews outside the web root; bound to session and user, expires after 30 minutes. */
final class ImportStore
{
    const MAX_PREVIEW_BYTES = 33554432;
    const MAX_USER_PREVIEWS = 5;
    private static function dir(): string
    {
        $dir =
            sys_get_temp_dir() .
            "/zabbix-import-export-" .
            substr(hash("sha256", \APP::getRootDir()), 0, 16);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create preview storage.");
        }
        $stat = lstat($dir);
        if (
            is_link($dir) ||
            (function_exists("posix_geteuid") &&
                ($stat["uid"] !== posix_geteuid() || ($stat["mode"] & 0077) !== 0))
        ) {
            throw new \RuntimeException("Preview storage permissions are unsafe.");
        }
        $resolved = realpath($dir);
        $normalized = str_replace("\\", "/", $resolved);
        foreach ([\APP::getRootDir(), $_SERVER["DOCUMENT_ROOT"] ?? ""] as $root) {
            if ($root === "") {
                continue;
            }
            $webroot = rtrim(str_replace("\\", "/", realpath($root) ?: $root), "/");
            if (
                !$resolved ||
                $normalized === $webroot ||
                strpos($normalized, $webroot . "/") === 0
            ) {
                throw new \RuntimeException("Preview storage must be outside the web root.");
            }
        }
        return $dir;
    }
    private static function owner(): string
    {
        return hash("sha256", \CSessionHelper::getId() . "|" . \CWebUser::$data["userid"]);
    }
    public static function create(array $data): string
    {
        $id = bin2hex(random_bytes(24));
        $data =
            ["owner" => self::owner(), "expires" => time() + 1800, "offset" => 0, "results" => []] +
            array_intersect_key($data, array_flip(["type", "records", "options", "preview"]));
        // Preserve business text while making retained JSON incapable of containing PHP/HTML tags.
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG);
        if (strlen($json) > self::MAX_PREVIEW_BYTES) {
            throw new \RuntimeException("Preview data is too large.");
        }
        $dir = self::dir();
        $lockpath = $dir . "/.quota.lock";
        if (is_link($lockpath)) {
            throw new \RuntimeException("Preview storage permissions are unsafe.");
        }
        $lock = fopen($lockpath, "c");
        if (!$lock) {
            throw new \RuntimeException("Cannot store preview.");
        }
        chmod($lockpath, 0600);
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException("Preview is busy.");
            }
            $own = [];
            $bytes = 0;
            $files = 0;
            foreach (glob($dir . "/*.json") as $old) {
                if (is_link($old)) {
                    continue;
                }
                $head = file_get_contents($old, false, null, 0, 512);
                preg_match('/"expires":(\d+)/', $head, $expiry);
                if (
                    (isset($expiry[1]) && (int) $expiry[1] < time()) ||
                    filemtime($old) < time() - 3600
                ) {
                    @unlink($old);
                    continue;
                }
                $bytes += filesize($old);
                $files++;
                if (strpos($head, '"owner":"' . self::owner() . '"') !== false) {
                    $own[] = $old;
                }
            }
            // Old previews from this same session can safely be replaced; their random IDs become invalid.
            usort($own, function ($a, $b) {
                return filemtime($a) <=> filemtime($b);
            });
            while (count($own) >= self::MAX_USER_PREVIEWS) {
                $old = array_shift($own);
                $bytes -= filesize($old);
                $files--;
                unlink($old);
            }
            if ($files >= 100 || $bytes + strlen($json) > 134217728) {
                throw new \RuntimeException("Preview storage quota exceeded.");
            }
            $path = $dir . "/" . $id . ".json";
            $f = fopen($path, "x");
            if (!$f) {
                throw new \RuntimeException("Cannot store preview.");
            }
            try {
                chmod($path, 0600);
                if (fwrite($f, $json) !== strlen($json)) {
                    throw new \RuntimeException("Cannot store preview.");
                }
            } finally {
                fclose($f);
            }
            return $id;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    public static function transaction(string $id, callable $fn): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $id)) {
            throw new \RuntimeException("Invalid preview token.");
        }
        $path = self::dir() . "/" . $id . ".json";
        if (is_link($path)) {
            throw new \RuntimeException("Invalid preview token.");
        }
        $f = @fopen($path, "r+");
        if (!$f) {
            throw new \RuntimeException("Preview expired.");
        }
        try {
            if (!flock($f, LOCK_EX)) {
                throw new \RuntimeException("Preview is busy.");
            }
            $stat = fstat($f);
            if (
                $stat["size"] > self::MAX_PREVIEW_BYTES * 2 ||
                (function_exists("posix_geteuid") &&
                    ($stat["uid"] !== posix_geteuid() || ($stat["mode"] & 0077) !== 0))
            ) {
                throw new \RuntimeException("Unsafe preview file.");
            }
            $data = json_decode(stream_get_contents($f), true, 32, JSON_THROW_ON_ERROR);
            if (($data["owner"] ?? "") !== self::owner() || ($data["expires"] ?? 0) < time()) {
                throw new \RuntimeException("Preview expired or belongs to another session.");
            }
            $fn($data);
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG);
            if (strlen($json) > self::MAX_PREVIEW_BYTES * 2) {
                foreach ($data["preview"] ?? [] as $i => $row) {
                    unset($data["preview"][$i]["diff"]);
                }
                $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG);
            }
            if (strlen($json) > self::MAX_PREVIEW_BYTES * 2) {
                throw new \RuntimeException("Preview data is too large.");
            }
            rewind($f);
            ftruncate($f, 0);
            if (fwrite($f, $json) !== strlen($json)) {
                throw new \RuntimeException("Cannot store preview.");
            }
            fflush($f);
            return $data;
        } finally {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }
}
