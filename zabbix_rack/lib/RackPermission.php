<?php
/**
 * 机房访问权限（关联 Zabbix 用户组 / 用户）
 * - 机房可配置 user_groups、users（可选）
 * - 两者均未配置时对所有用户可见
 */

namespace Modules\ZabbixRack\Lib;

require_once __DIR__ . '/ZabbixVersion.php';
require_once __DIR__ . '/LanguageManager.php';

class RackPermission {

    /** @var string|null */
    private static $currentUserId = null;

    /** @var array<string, bool>|null */
    private static $currentUserGroupIds = null;

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    public static function normalizeIdList($values): array {
        if (!is_array($values)) {
            if ($values === null || $values === '') {
                return [];
            }
            if (is_string($values)) {
                $trimmed = trim($values);
                if ($trimmed === '') {
                    return [];
                }
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                } else {
                    $values = preg_split('/\s*,\s*/', $trimmed) ?: [];
                }
            } else {
                return [];
            }
        }

        $ids = [];
        foreach ($values as $value) {
            $id = trim((string) $value);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * 写入机房前规范化权限字段；空权限时不写入键名以保持旧版 config 兼容。
     *
     * @param array<string, mixed> $room
     * @return array<string, mixed>
     */
    public static function applyRoomPermissions(array $room): array {
        $userGroups = self::normalizeIdList($room['user_groups'] ?? []);
        $users = self::normalizeIdList($room['users'] ?? []);

        unset($room['user_groups'], $room['users']);

        if ($userGroups !== []) {
            $room['user_groups'] = $userGroups;
        }
        if ($users !== []) {
            $room['users'] = $users;
        }

        return $room;
    }

    /**
     * @param array<string, mixed> $room
     */
    public static function roomIsPublic(array $room): bool {
        return self::normalizeIdList($room['user_groups'] ?? []) === []
            && self::normalizeIdList($room['users'] ?? []) === [];
    }

    public static function isSuperAdmin(): bool {
        if (!defined('USER_TYPE_SUPER_ADMIN')) {
            return false;
        }

        try {
            if (class_exists('\CWebUser') && is_array(\CWebUser::$data ?? null)) {
                return (int) (\CWebUser::$data['type'] ?? 0) >= USER_TYPE_SUPER_ADMIN;
            }
            if (class_exists('\CWebUser') && method_exists('\CWebUser', 'getType')) {
                return (int) \CWebUser::getType() >= USER_TYPE_SUPER_ADMIN;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * 机柜配置页及机房/机柜 CRUD 仅超级管理员可访问。
     */
    public static function canAccessManage(): bool {
        return self::isSuperAdmin();
    }

    /**
     * @return array{user_groups: array<int, string>, users: array<int, string>}
     */
    public static function getAllPermissionOptionIds(): array {
        return [
            'user_groups' => array_column(self::getUserGroupOptions(), 'usrgrpid'),
            'users'         => array_column(self::getUserOptions(), 'userid'),
        ];
    }

    /**
     * 保存前规范化：全选用户组与用户视为全员可见（不写权限字段）。
     *
     * @param array<string, mixed> $room
     * @param array<int, string> $allGroupIds
     * @param array<int, string> $allUserIds
     * @return array<string, mixed>
     */
    public static function finalizeRoomPermissions(array $room, array $allGroupIds, array $allUserIds): array {
        $userGroups = self::normalizeIdList($room['user_groups'] ?? []);
        $users = self::normalizeIdList($room['users'] ?? []);
        $allGroupIds = self::normalizeIdList($allGroupIds);
        $allUserIds = self::normalizeIdList($allUserIds);

        if ($allGroupIds !== [] && $allUserIds !== []
            && self::idListsEqual($userGroups, $allGroupIds)
            && self::idListsEqual($users, $allUserIds)) {
            $room['user_groups'] = [];
            $room['users'] = [];
        }

        return self::applyRoomPermissions($room);
    }

    /**
     * @param array<int, string> $a
     * @param array<int, string> $b
     */
    private static function idListsEqual(array $a, array $b): bool {
        $a = self::normalizeIdList($a);
        $b = self::normalizeIdList($b);
        sort($a);
        sort($b);

        return $a === $b;
    }

    public static function getCurrentUserId(): string {
        if (self::$currentUserId !== null) {
            return self::$currentUserId;
        }

        $userid = '';

        try {
            if (class_exists('\CWebUser')) {
                if (is_array(\CWebUser::$data ?? null) && isset(\CWebUser::$data['userid'])) {
                    $userid = (string) \CWebUser::$data['userid'];
                } elseif (method_exists('\CWebUser', 'get')) {
                    $userid = (string) \CWebUser::get('userid');
                }
            }
        } catch (\Throwable $e) {
        }

        if ($userid === '' || $userid === '0') {
            if (isset($_SESSION['userid'])) {
                $userid = (string) $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = (string) $_SESSION['user']['userid'];
            }
        }

        self::$currentUserId = $userid;
        return self::$currentUserId;
    }

    /**
     * @param array<int, string> $allowedIds
     */
    private static function idMatchesList(string $id, array $allowedIds): bool {
        $id = trim($id);
        if ($id === '' || $id === '0') {
            return false;
        }

        foreach ($allowedIds as $allowedId) {
            if ($id === trim((string) $allowedId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    public static function getCurrentUserGroupIds(): array {
        if (self::$currentUserGroupIds !== null) {
            return self::$currentUserGroupIds;
        }

        $map = [];
        $userid = self::getCurrentUserId();

        if ($userid !== '') {
            try {
                $groups = \API::UserGroup()->get([
                    'output'  => ['usrgrpid'],
                    'userids' => $userid,
                ]);
                foreach ((is_array($groups) ? $groups : []) as $group) {
                    $id = (string) ($group['usrgrpid'] ?? '');
                    if ($id !== '') {
                        $map[$id] = true;
                    }
                }
            } catch (\Throwable $e) {
                try {
                    $selectParam = ZabbixVersion::isVersion7() ? 'selectUsrgrps' : 'selectGroups';
                    $users = \API::User()->get([
                        'output'   => ['userid'],
                        'userids'  => $userid,
                        $selectParam => ['usrgrpid'],
                    ]);
                    $groups = [];
                    if (!empty($users[0]['usrgrps'])) {
                        $groups = $users[0]['usrgrps'];
                    } elseif (!empty($users[0]['groups'])) {
                        $groups = $users[0]['groups'];
                    }
                    foreach ($groups as $group) {
                        $id = (string) ($group['usrgrpid'] ?? '');
                        if ($id !== '') {
                            $map[$id] = true;
                        }
                    }
                } catch (\Throwable $e2) {
                }
            }
        }

        self::$currentUserGroupIds = $map;
        return self::$currentUserGroupIds;
    }

    /**
     * @param array<string, mixed> $room
     */
    public static function userCanAccessRoom(
        array $room,
        ?string $userid = null,
        ?array $userGroupIdMap = null
    ): bool {
        if (self::isSuperAdmin()) {
            return true;
        }

        if (self::roomIsPublic($room)) {
            return true;
        }

        $userid = $userid ?? self::getCurrentUserId();
        $allowedUsers = self::normalizeIdList($room['users'] ?? []);
        if (self::idMatchesList($userid, $allowedUsers)) {
            return true;
        }

        $allowedGroups = self::normalizeIdList($room['user_groups'] ?? []);
        if ($allowedGroups === []) {
            return false;
        }

        $userGroupIdMap = $userGroupIdMap ?? self::getCurrentUserGroupIds();
        foreach ($allowedGroups as $groupId) {
            if (isset($userGroupIdMap[(string) $groupId])) {
                return true;
            }
        }

        return false;
    }

    public static function userCanAccessRoomId(string $roomId, ?array $rooms = null): bool {
        $roomId = trim($roomId);
        if ($roomId === '') {
            return false;
        }

        $rooms = $rooms ?? RackConfig::getRooms();
        foreach ($rooms as $room) {
            if ((string) ($room['id'] ?? '') === $roomId) {
                return self::userCanAccessRoom($room);
            }
        }

        return false;
    }

    public static function userCanAccessRackId(string $rackId): bool {
        $rack = RackConfig::getRack($rackId);
        if ($rack === null) {
            return false;
        }

        $room = RackConfig::getRoom((string) ($rack['room_id'] ?? ''));
        if ($room === null) {
            return false;
        }

        return self::userCanAccessRoom($room);
    }

    /**
     * 校验当前用户是否可操作指定主机上的机柜标签（分配/移除）。
     */
    public static function userCanAccessHostId(string $hostId): bool {
        if (self::isSuperAdmin()) {
            return true;
        }

        $hostId = trim($hostId);
        if ($hostId === '') {
            return false;
        }

        try {
            $hosts = \API::Host()->get([
                'output'     => ['hostid'],
                'selectTags' => 'extend',
                'hostids'    => [$hostId],
            ]);
        } catch (\Throwable $e) {
            return false;
        }

        if (empty($hosts)) {
            return false;
        }

        $roomName = '';
        foreach (($hosts[0]['tags'] ?? []) as $tag) {
            if (($tag['tag'] ?? '') === 'rack_room' && ($tag['value'] ?? '') !== '') {
                $roomName = (string) $tag['value'];
                break;
            }
        }

        if ($roomName === '') {
            return true;
        }

        foreach (RackConfig::getRooms() as $room) {
            if ((string) ($room['name'] ?? '') === $roomName) {
                return self::userCanAccessRoom($room);
            }
        }

        return false;
    }

    public static function denyAccessJson(): void {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => LanguageManager::t('permission_denied'),
        ]);
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $rooms
     * @return array<int, array<string, mixed>>
     */
    public static function filterRoomsForCurrentUser(array $rooms): array {
        if (self::isSuperAdmin()) {
            return $rooms;
        }

        $userid = self::getCurrentUserId();
        $groupMap = self::getCurrentUserGroupIds();
        $filtered = [];

        foreach ($rooms as $room) {
            if (self::userCanAccessRoom($room, $userid, $groupMap)) {
                $filtered[] = $room;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $racks
     * @param array<int, array<string, mixed>>|null $accessibleRooms
     * @return array<int, array<string, mixed>>
     */
    public static function filterRacksForCurrentUser(array $racks, ?array $accessibleRooms = null): array {
        if (self::isSuperAdmin()) {
            return $racks;
        }

        $accessibleRooms = $accessibleRooms ?? self::filterRoomsForCurrentUser(RackConfig::getRooms());
        $allowedRoomIds = [];
        foreach ($accessibleRooms as $room) {
            $allowedRoomIds[(string) ($room['id'] ?? '')] = true;
        }

        $filtered = [];
        foreach ($racks as $rack) {
            $roomId = (string) ($rack['room_id'] ?? '');
            if ($roomId !== '' && isset($allowedRoomIds[$roomId])) {
                $filtered[] = $rack;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $room
     * @param array<int, array<string, string>> $userGroupOptions usrgrpid => name
     * @param array<int, array<string, string>> $userOptions userid => label
     */
    public static function formatRoomPermissionSummary(
        array $room,
        array $userGroupOptions = [],
        array $userOptions = []
    ): string {
        if (self::roomIsPublic($room)) {
            return LanguageManager::t('permission_public');
        }

        $parts = [];
        $groupIds = self::normalizeIdList($room['user_groups'] ?? []);
        $userIds = self::normalizeIdList($room['users'] ?? []);

        if ($groupIds !== []) {
            $names = [];
            foreach ($groupIds as $groupId) {
                $names[] = $userGroupOptions[$groupId]['name'] ?? ('#' . $groupId);
            }
            $parts[] = LanguageManager::tf('permission_user_groups_count', count($groupIds))
                . ': ' . implode(', ', $names);
        }

        if ($userIds !== []) {
            $names = [];
            foreach ($userIds as $userId) {
                $names[] = $userOptions[$userId]['label'] ?? ('#' . $userId);
            }
            $parts[] = LanguageManager::tf('permission_users_count', count($userIds))
                . ': ' . implode(', ', $names);
        }

        return $parts !== [] ? implode('; ', $parts) : LanguageManager::t('permission_restricted');
    }

    /**
     * @return array<int, array{usrgrpid:string,name:string}>
     */
    public static function getUserGroupOptions(): array {
        try {
            $groups = \API::UserGroup()->get([
                'output'    => ['usrgrpid', 'name'],
                'sortfield' => 'name',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $options = [];
        foreach ((is_array($groups) ? $groups : []) as $group) {
            $id = (string) ($group['usrgrpid'] ?? '');
            if ($id === '') {
                continue;
            }
            $options[] = [
                'usrgrpid' => $id,
                'name'     => (string) ($group['name'] ?? $id),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{userid:string,username:string,name:string,label:string}>
     */
    public static function getUserOptions(): array {
        try {
            $users = \API::User()->get([
                'output'    => ['userid', 'username', 'name'],
                'sortfield' => 'username',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $options = [];
        foreach ((is_array($users) ? $users : []) as $user) {
            $id = (string) ($user['userid'] ?? '');
            if ($id === '') {
                continue;
            }
            $username = (string) ($user['username'] ?? $id);
            $name = trim((string) ($user['name'] ?? ''));
            $label = $name !== '' && $name !== $username ? ($name . ' (' . $username . ')') : $username;
            $options[] = [
                'userid'   => $id,
                'username' => $username,
                'name'     => $name,
                'label'    => $label,
            ];
        }

        return $options;
    }
}
