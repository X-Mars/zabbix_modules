<?php

namespace Modules\ZabbixIm\Lib;

/**
 * 记录由模块创建/管理的用户组与用户，避免误删系统或手工对象
 */
class SyncRegistry {

    private static $data = null;

    public static function getRegistryPath(): string {
        return dirname(__DIR__) . '/data/sync_registry.json';
    }

    public static function load(): array {
        if (self::$data !== null) {
            return self::$data;
        }

        $defaults = [
            'version' => 1,
            'groups'  => [],
            'users'   => [],
        ];

        $path = self::getRegistryPath();
        if (!is_file($path) || !is_readable($path)) {
            self::$data = $defaults;
            return self::$data;
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::$data = $defaults;
            return self::$data;
        }

        self::$data = array_replace_recursive($defaults, $decoded);
        return self::$data;
    }

    public static function save(array $data): bool {
        self::$data = $data;
        $path = self::getRegistryPath();
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('IM Sync: failed to create registry directory: ' . $dir);
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return @file_put_contents($path, $json . "\n") !== false;
    }

    public static function isManagedGroup(string $usrgrpid): bool {
        $data = self::load();
        return isset($data['groups'][(string) $usrgrpid]);
    }

    public static function isManagedUser(string $userid): bool {
        $data = self::load();
        return isset($data['users'][(string) $userid]);
    }

    public static function findGroupIdByDeptId(string $imDeptId): ?string {
        $data = self::load();
        foreach ($data['groups'] as $usrgrpid => $meta) {
            if ((string) ($meta['im_dept_id'] ?? '') === $imDeptId) {
                return (string) $usrgrpid;
            }
        }
        return null;
    }

    public static function registerGroup(array &$data, string $usrgrpid, string $name, string $imDeptId): void {
        $data['groups'][(string) $usrgrpid] = [
            'name'       => $name,
            'im_dept_id' => $imDeptId,
        ];
    }

    public static function registerUser(
        array &$data,
        string $userid,
        string $imUserId,
        string $username,
        string $name,
        string $origin = 'created'
    ): void {
        $data['users'][(string) $userid] = [
            'im_user_id' => $imUserId,
            'username'   => $username,
            'name'       => $name,
            'origin'     => $origin === 'linked' ? 'linked' : 'created',
        ];
    }

    public static function isCreatedUser(string $userid): bool {
        $data = self::load();
        $meta = $data['users'][(string) $userid] ?? null;
        return is_array($meta) && (($meta['origin'] ?? 'created') === 'created');
    }

    public static function removeGroup(array &$data, string $usrgrpid): void {
        unset($data['groups'][(string) $usrgrpid]);
    }

    public static function removeUser(array &$data, string $userid): void {
        unset($data['users'][(string) $userid]);
    }

    public static function getManagedGroupCount(): int {
        $data = self::load();
        return count($data['groups'] ?? []);
    }

    public static function getManagedUserCount(): int {
        $data = self::load();
        return count($data['users'] ?? []);
    }
}
