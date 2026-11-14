<?php

namespace Modules\ZabbixBackupMysql\Lib;

final class BackupFile {
    public const DOWNLOAD_LIMIT = 500000000;
    public const DEFAULT_DIRECTORY = '/tmp/zabbix';

    public static function filename(): string {
        return 'zabbix_backup_' . date('Y-m-d_H-i-s') . '.sql';
    }

    public static function shouldSave(string $destination, int $bytes): bool {
        return $destination === 'server' || $bytes > self::DOWNLOAD_LIMIT;
    }

    public static function write($file, string $chunk): void {
        if (@fwrite($file, $chunk) !== strlen($chunk)) {
            throw new \RuntimeException('Could not write the backup. Check available disk space.');
        }
    }

    public static function save($file, string $filename, string $path): string {
        $directory = self::directory($path);
        $stage = $directory . '/.backup-' . bin2hex(random_bytes(16));
        $previous = umask(0077);
        try {
            $output = @fopen($stage, 'xb');
        } finally {
            umask($previous);
        }
        if ($output === false) {
            throw new \RuntimeException('Could not create a private backup file.');
        }

        try {
            if (!rewind($file) || @stream_copy_to_stream($file, $output) !== fstat($file)['size']
                    || !fflush($output)) {
                throw new \RuntimeException('Could not write the backup. Check available disk space.');
            }

            $target = $directory . '/' . $filename;
            if (!@link($stage, $target)) {
                throw new \RuntimeException('Could not save the backup. Check permissions or retry in a second.');
            }
            return $target;
        } finally {
            fclose($output);
            @unlink($stage);
        }
    }

    private static function directory(string $path): string {
        $path = rtrim(trim($path), '/');
        $config = dirname(__DIR__) . '/config.local.php';
        $roots = [self::DEFAULT_DIRECTORY];
        if (is_file($config)) {
            $configured = require $config;
            if (!is_array($configured)) {
                throw new \RuntimeException('The server backup directory configuration is invalid.');
            }
            $roots = array_merge($roots, $configured);
        }

        foreach ($roots as $root) {
            if (!is_string($root) || $root === '' || $root[0] !== '/' || $root === '/') {
                continue;
            }
            $root = rtrim($root, '/');
            if ($path !== $root && strpos($path, $root . '/') !== 0) {
                continue;
            }
            if ($path === self::DEFAULT_DIRECTORY && !file_exists($path) && !is_link($path)) {
                @mkdir($path, 0700);
                clearstatcache(true, $path);
            }

            $canonical = realpath($path);
            if ($canonical !== $path || !self::privateDirectory($root) || !self::privateDirectory($path)) {
                break;
            }
            foreach ([\APP::getRootDir(), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $webDirectory) {
                $webRoot = $webDirectory === '' ? false : realpath($webDirectory);
                if ($webRoot !== false && ($path === $webRoot || strpos($path, rtrim($webRoot, '/') . '/') === 0)) {
                    throw new \RuntimeException('Choose a private backup directory outside the web root.');
                }
            }
            return $path;
        }
        throw new \RuntimeException('Choose a private, writable directory configured for backups.');
    }

    private static function privateDirectory(string $path): bool {
        if (!is_dir($path) || !is_writable($path) || (fileperms($path) & 0077) !== 0) {
            return false;
        }
        return !function_exists('posix_geteuid') || fileowner($path) === posix_geteuid();
    }
}
