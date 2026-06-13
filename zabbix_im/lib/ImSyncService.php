<?php

namespace Modules\ZabbixIm\Lib;

use API;

require_once __DIR__ . '/ZabbixApiHelper.php';
require_once __DIR__ . '/LanguageManager.php';

/**
 * 将 IM 部门与用户同步到 Zabbix 用户群组
 * 仅对模块注册表中的对象执行增删改，系统/手工对象不受影响
 */
class ImSyncService {

    /** @var ImProviderInterface */
    private $provider;

    /** @var array */
    private $config;

    /** @var array */
    private $registry;

    /** name => usrgrpid */
    private $groupMap = [];

    /** usrgrpid => userid[] */
    private $groupMembers = [];

    /** match key => userid */
    private $userMap = [];

    /** userid => true（Zabbix 中实际存在的用户） */
    private $existingUserIds = [];

    /** im_user_id => userid (managed users) */
    private $managedUserByImId = [];

    /** @var string */
    private $lastUserCreateError = '';

    /** @var bool */
    private $lastUserWasCreated = false;

    /** @var mixed */
    private $lastUserCreateApiResponse = null;

    /** @var array|null */
    private $lastUserCreateParams = null;

    /** @var string */
    private $lastCreatedPassword = '';

    /** @var string */
    private $lastGroupUpdateError = '';

    public function __construct(array $config) {
        $this->config = $config;
        $this->provider = ImProviderFactory::create($config);
        $this->registry = SyncRegistry::load();
    }

    public function syncGroups(bool $dryRun = false): array {
        return $this->runGroupSync($dryRun, false);
    }

    public function syncUsers(bool $dryRun = false): array {
        return $this->runUserSync($dryRun);
    }

    public function sync(bool $dryRun = false): array {
        return $this->syncGroups($dryRun);
    }

    public function preview(): array {
        $groupPreview = $this->runGroupSync(true, false);
        $userPreview = $this->runUserSync(true);

        $userDetailsByGroup = [];
        foreach ($userPreview['details'] as $row) {
            $userDetailsByGroup[(string) ($row['group_name'] ?? '')] = $row;
        }

        foreach ($groupPreview['details'] as &$row) {
            $groupName = (string) ($row['group_name'] ?? '');
            if (!isset($userDetailsByGroup[$groupName])) {
                continue;
            }

            $userRow = $userDetailsByGroup[$groupName];
            $row['im_users'] = $userRow['im_users'] ?? $row['im_users'];
            $row['matched_users'] = $userRow['matched_users'] ?? 0;
            $row['unmatched_users'] = $userRow['unmatched_users'] ?? [];
            $row['managed_group'] = !empty($userRow['managed_group']);
            if (!empty($userRow['message'])) {
                $row['group_sync_hint'] = $userRow['message'];
            }
        }
        unset($row);

        $groupPreview['users_matched'] = $userPreview['users_matched'];
        $groupPreview['users_unmatched'] = $userPreview['users_unmatched'];
        $groupPreview['users_created'] = $userPreview['users_created'];
        $groupPreview['unmatched_users'] = $userPreview['unmatched_users'];
        $groupPreview['user_groups_pending'] = $userPreview['groups_skipped'];
        $groupPreview['im_users_raw'] = $userPreview['im_users_raw'] ?? [];

        return $this->attachProviderApiLog($groupPreview);
    }

    private function attachProviderApiLog(array $summary): array {
        $summary['im_api_log'] = $this->provider->getApiDebugLog();
        return $summary;
    }

    private function runGroupSync(bool $dryRun, bool $syncMembership): array {
        $departments = $this->provider->getDepartments();
        $departments = $this->buildDepartmentGroups($departments);

        $this->loadZabbixGroups();
        $this->loadZabbixGroupMembers();
        $this->loadZabbixUsers();
        $this->buildManagedUserIndex();

        $groupsCreated = 0;
        $groupsRenamed = 0;
        $groupsExisting = 0;
        $groupsUpdated = 0;
        $groupsSkipped = 0;
        $groupsDeleted = 0;
        $groupsFailed = 0;
        $usersCreated = 0;
        $usersUpdated = 0;
        $usersMatched = 0;
        $usersUnmatched = 0;
        $usersDeleted = 0;
        $details = [];

        $activeGroupNames = [];
        $activeDeptIds = [];
        $activeImUserIds = [];
        $uniqueMatched = [];
        $uniqueUnmatched = [];
        $imUsersRaw = [];

        foreach ($departments as $dept) {
            $groupName = (string) ($dept['group_name'] ?? '');
            $imDeptId = (string) ($dept['id'] ?? '');
            if ($groupName === '' || $imDeptId === '') {
                continue;
            }

            $activeGroupNames[$groupName] = true;
            $activeDeptIds[$imDeptId] = true;
            $imUsers = $this->provider->getDepartmentUsers($imDeptId);
            $resolvedUserIds = [];
            $deptUnmatched = [];

            foreach ($imUsers as $imUser) {
                $imUserId = (string) ($imUser['id'] ?? '');
                if ($imUserId !== '') {
                    $activeImUserIds[$imUserId] = true;
                }

                if ($syncMembership || $dryRun) {
                    $userid = $this->resolveOrCreateUser($imUser, $dryRun, $usersCreated, $usersUpdated, $usersMatched);
                    $this->recordImUserDebug(
                        $imUsersRaw,
                        $imUser,
                        (string) ($dept['name'] ?? ''),
                        $userid,
                        $dryRun
                    );
                    if ($userid !== null && $imUserId !== '') {
                        $resolvedUserIds[$userid] = $userid;
                        $uniqueMatched[$imUserId] = true;
                    } elseif ($imUserId !== '') {
                        $label = $this->formatImUserLabel($imUser);
                        if ($this->lastUserCreateError !== '') {
                            $label .= ' - ' . $this->lastUserCreateError;
                        }
                        $deptUnmatched[$imUserId] = $label;
                        $uniqueUnmatched[$imUserId] = $label;
                    }
                }
            }

            $deptUnmatched = array_values($deptUnmatched);
            $resolvedUserIds = array_values($resolvedUserIds);
            sort($resolvedUserIds);

            if ($dryRun) {
                $usrgrpid = SyncRegistry::findGroupIdByDeptId($imDeptId);
                $details[] = [
                    'department'      => (string) ($dept['name'] ?? ''),
                    'group_name'      => $groupName,
                    'im_users'        => count($imUsers),
                    'matched_users'   => count($resolvedUserIds),
                    'unmatched_users' => $deptUnmatched,
                    'group_exists'    => $usrgrpid !== null || isset($this->groupMap[$groupName]),
                    'managed_group'   => $usrgrpid !== null,
                ];
                continue;
            }

            $groupResult = $this->resolveManagedGroup($groupName, $imDeptId, $groupsCreated, $groupsRenamed, $groupsExisting, $groupsSkipped);
            if ($groupResult === null) {
                $groupsFailed++;
                $details[] = [
                    'department' => (string) ($dept['name'] ?? ''),
                    'group_name' => $groupName,
                    'status'     => 'skipped',
                    'im_users'   => count($imUsers),
                    'matched'    => count($resolvedUserIds),
                    'unmatched'  => $deptUnmatched,
                ];
                continue;
            }

            [$usrgrpid, $groupStatus] = $groupResult;

            if ($syncMembership) {
                if ($this->updateManagedGroupUsers($usrgrpid, $resolvedUserIds)) {
                    if ($groupStatus === 'existing' || $groupStatus === 'renamed') {
                        $groupsUpdated++;
                    }
                } else {
                    $groupsFailed++;
                    $groupStatus = 'failed';
                }
            } elseif ($groupStatus === 'existing' || $groupStatus === 'renamed') {
                $groupsUpdated++;
            }

            $details[] = [
                'department' => (string) ($dept['name'] ?? ''),
                'group_name' => $groupName,
                'status'     => $groupStatus,
                'im_users'   => count($imUsers),
                'matched'    => count($resolvedUserIds),
                'unmatched'  => $deptUnmatched,
            ];
        }

        if (!$dryRun) {
            $cleanup = $this->finalizeOrphanCleanup(
                array_keys($activeDeptIds),
                array_keys($activeImUserIds),
                ConfigManager::shouldRemoveOrphanUsers()
            );
            $groupsDeleted = $cleanup['groups_deleted'];
            $usersDeleted = $cleanup['users_deleted'];
            SyncRegistry::save($this->registry);
        }

        $usersMatched = count($uniqueMatched);
        $usersUnmatched = count($uniqueUnmatched);

        return $this->attachProviderApiLog([
            'type'            => $syncMembership ? 'users' : 'groups',
            'provider'        => ConfigManager::getProvider(),
            'departments'     => count($departments),
            'groups_created'  => $groupsCreated,
            'groups_renamed'  => $groupsRenamed,
            'groups_existing' => $groupsExisting,
            'groups_updated'  => $groupsUpdated,
            'groups_skipped'  => $groupsSkipped,
            'groups_deleted'  => $groupsDeleted,
            'groups_failed'   => $groupsFailed,
            'users_created'   => $usersCreated,
            'users_updated'   => $usersUpdated,
            'users_matched'   => $usersMatched,
            'users_unmatched' => $usersUnmatched,
            'users_deleted'   => $usersDeleted,
            'unmatched_users' => array_values($uniqueUnmatched),
            'im_users_raw'    => array_values($imUsersRaw),
            'details'         => $details,
        ]);
    }

    /**
     * 清理当前 IM 接口范围内已不存在的模块管理用户组/用户
     */
    private function finalizeOrphanCleanup(array $activeDeptIds, array $activeImUserIds, bool $cleanupUsers): array {
        $groupsDeleted = 0;
        $usersDeleted = 0;

        if (ConfigManager::shouldRemoveOrphans()) {
            $groupsDeleted = $this->removeOrphanManagedGroupsByDeptIds($activeDeptIds);
        }

        if ($cleanupUsers && ConfigManager::shouldRemoveOrphanUsers()) {
            $usersDeleted = $this->removeOrphanManagedUsers($activeImUserIds);
        }

        $this->cleanupLinkedUsers($activeImUserIds);

        return [
            'groups_deleted' => $groupsDeleted,
            'users_deleted'  => $usersDeleted,
        ];
    }

    private function runUserSync(bool $dryRun): array {
        $departments = $this->provider->getDepartments();
        $departments = $this->buildDepartmentGroups($departments);

        $this->loadZabbixGroups();
        $this->loadZabbixGroupMembers();
        $this->loadZabbixUsers();
        $this->buildManagedUserIndex();

        if (!$dryRun && ConfigManager::shouldAutoCreateUsers()) {
            $configError = $this->validateUserCreateConfig();
            if ($configError !== null) {
                throw new \RuntimeException($configError);
            }
        }

        $groupsUpdated = 0;
        $groupsSkipped = 0;
        $groupsFailed = 0;
        $usersCreated = 0;
        $usersUpdated = 0;
        $usersMatched = 0;
        $usersUnmatched = 0;
        $usersDeleted = 0;
        $details = [];
        $activeImUserIds = [];
        $activeDeptIds = [];
        $uniqueMatched = [];
        $uniqueUnmatched = [];
        $imUsersRaw = [];
        $usersSyncList = [];

        foreach ($departments as $dept) {
            $groupName = (string) ($dept['group_name'] ?? '');
            $imDeptId = (string) ($dept['id'] ?? '');
            if ($groupName === '' || $imDeptId === '') {
                continue;
            }

            $activeDeptIds[$imDeptId] = true;

            $groupResolve = $this->resolveUserSyncGroup($groupName, $imDeptId, $dryRun);
            $usrgrpid = $groupResolve['usrgrpid'];
            $groupReady = $groupResolve['ready'];

            if (!$groupReady && !$dryRun) {
                $groupsSkipped++;
                $details[] = [
                    'department' => (string) ($dept['name'] ?? ''),
                    'group_name' => $groupName,
                    'status'     => 'skipped',
                    'im_users'   => 0,
                    'matched'    => 0,
                    'unmatched'  => [],
                    'message'    => LanguageManager::t('User group not synced yet'),
                ];
                continue;
            }

            $imUsers = $this->provider->getDepartmentUsers($imDeptId);
            $resolvedUserIds = [];
            $deptUnmatched = [];

            foreach ($imUsers as $imUser) {
                $imUserId = (string) ($imUser['id'] ?? '');
                if ($imUserId !== '') {
                    $activeImUserIds[$imUserId] = true;
                }

                $userid = $this->resolveOrCreateUser($imUser, $dryRun, $usersCreated, $usersUpdated, $usersMatched);
                $this->recordImUserDebug(
                    $imUsersRaw,
                    $imUser,
                    (string) ($dept['name'] ?? ''),
                    $userid,
                    $dryRun
                );
                if ($imUserId !== '') {
                    $this->recordUserSyncListEntry(
                        $usersSyncList,
                        $imUser,
                        (string) ($dept['name'] ?? ''),
                        $userid,
                        $dryRun
                    );
                }
                if ($userid !== null && $imUserId !== '') {
                    $resolvedUserIds[$userid] = $userid;
                    $uniqueMatched[$imUserId] = true;
                } elseif ($imUserId !== '') {
                    $label = $this->formatImUserLabel($imUser);
                    if ($this->lastUserCreateError !== '') {
                        $label .= ' - ' . $this->lastUserCreateError;
                    }
                    $deptUnmatched[$imUserId] = $label;
                    $uniqueUnmatched[$imUserId] = $label;
                }
            }

            $deptUnmatched = array_values($deptUnmatched);
            $resolvedUserIds = array_values($resolvedUserIds);
            sort($resolvedUserIds);

            if ($dryRun) {
                $details[] = [
                    'department'      => (string) ($dept['name'] ?? ''),
                    'group_name'      => $groupName,
                    'im_users'        => count($imUsers),
                    'matched_users'   => count($resolvedUserIds),
                    'unmatched_users' => $deptUnmatched,
                    'managed_group'   => $groupReady,
                    'message'         => $groupReady ? '' : LanguageManager::t('User group not synced yet'),
                ];
                continue;
            }

            if ($usrgrpid === null || !$groupReady) {
                $groupsSkipped++;
                continue;
            }

            $groupError = '';
            if ($this->updateManagedGroupUsers($usrgrpid, $resolvedUserIds)) {
                $groupsUpdated++;
                $status = 'updated';
            } else {
                $groupsFailed++;
                $status = 'failed';
                $groupError = $this->lastGroupUpdateError;
            }

            $details[] = [
                'department' => (string) ($dept['name'] ?? ''),
                'group_name' => $groupName,
                'status'     => $status,
                'im_users'   => count($imUsers),
                'matched'    => count($resolvedUserIds),
                'unmatched'  => $deptUnmatched,
                'message'    => $groupError,
            ];
        }

        $groupsDeleted = 0;

        if (!$dryRun) {
            $cleanup = $this->finalizeOrphanCleanup(array_keys($activeDeptIds), array_keys($activeImUserIds), true);
            $usersDeleted = $cleanup['users_deleted'];
            $groupsDeleted = $cleanup['groups_deleted'];
            SyncRegistry::save($this->registry);
        }

        $usersMatched = count($uniqueMatched);
        $usersUnmatched = count($uniqueUnmatched);

        return $this->attachProviderApiLog([
            'type'            => 'users',
            'provider'        => ConfigManager::getProvider(),
            'departments'     => count($departments),
            'groups_created'  => 0,
            'groups_renamed'  => 0,
            'groups_existing' => 0,
            'groups_updated'  => $groupsUpdated,
            'groups_skipped'  => $groupsSkipped,
            'groups_deleted'  => $groupsDeleted,
            'groups_failed'   => $groupsFailed,
            'users_created'   => $usersCreated,
            'users_updated'   => $usersUpdated,
            'users_matched'   => $usersMatched,
            'users_unmatched' => $usersUnmatched,
            'users_deleted'   => $usersDeleted,
            'unmatched_users' => array_values($uniqueUnmatched),
            'im_users_raw'    => array_values($imUsersRaw),
            'users_sync_list' => array_values($usersSyncList),
            'details'         => $details,
        ]);
    }

    private function validateUserCreateConfig(): ?string {
        if (!ConfigManager::shouldAutoCreateUsers()) {
            return null;
        }
        if (!ZabbixApiHelper::canCreateUsers()) {
            return LanguageManager::t('Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.');
        }
        if (ZabbixVersion::isVersion7() && $this->resolveDefaultRoleId() === '') {
            return LanguageManager::t('default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.');
        }
        return null;
    }

    private function buildDepartmentGroups(array $departments): array {
        $prefix = ConfigManager::getGroupPrefix();
        $useFullPath = ConfigManager::useFullPath();
        $separator = ConfigManager::getPathSeparator();

        $byId = [];
        foreach ($departments as $dept) {
            $byId[(string) $dept['id']] = $dept;
        }

        $result = [];
        foreach ($departments as $dept) {
            $name = trim((string) ($dept['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if ($useFullPath) {
                $path = $this->buildDepartmentPath($dept, $byId, $separator);
                $groupName = $prefix . $path;
            } else {
                $groupName = $prefix . $name;
            }

            $dept['group_name'] = $groupName;
            $result[] = $dept;
        }

        usort($result, static function ($a, $b) {
            return strcmp((string) $a['group_name'], (string) $b['group_name']);
        });

        return $result;
    }

    private function buildDepartmentPath(array $dept, array $byId, string $separator): string {
        $parts = [trim((string) ($dept['name'] ?? ''))];
        $parentId = (string) ($dept['parent_id'] ?? '0');
        $guard = 0;

        while ($parentId !== '' && $parentId !== '0' && isset($byId[$parentId]) && $guard < 30) {
            $parent = $byId[$parentId];
            array_unshift($parts, trim((string) ($parent['name'] ?? '')));
            $parentId = (string) ($parent['parent_id'] ?? '0');
            $guard++;
        }

        $parts = array_values(array_filter($parts, static function ($part) {
            return $part !== '';
        }));

        return implode($separator, $parts);
    }

    private function loadZabbixGroups(): void {
        $this->groupMap = [];

        try {
            $groups = API::UserGroup()->get([
                'output' => ['usrgrpid', 'name'],
            ]);
        } catch (\Throwable $e) {
            error_log('IM Sync: user group fetch failed: ' . $e->getMessage());
            return;
        }

        foreach ((is_array($groups) ? $groups : []) as $group) {
            $name = (string) ($group['name'] ?? '');
            $id = (string) ($group['usrgrpid'] ?? '');
            if ($name !== '' && $id !== '') {
                $this->groupMap[$name] = $id;
            }
        }
    }

    private function loadZabbixGroupMembers(): void {
        $this->groupMembers = [];

        try {
            $groups = API::UserGroup()->get([
                'output'      => ['usrgrpid', 'name'],
                'selectUsers' => ['userid'],
            ]);
        } catch (\Throwable $e) {
            error_log('IM Sync: user group members fetch failed: ' . $e->getMessage());
            return;
        }

        foreach ((is_array($groups) ? $groups : []) as $group) {
            $id = (string) ($group['usrgrpid'] ?? '');
            if ($id === '') {
                continue;
            }

            $members = [];
            foreach (($group['users'] ?? []) as $user) {
                $userid = (string) ($user['userid'] ?? '');
                if ($userid !== '') {
                    $members[] = $userid;
                }
            }
            $this->groupMembers[$id] = $members;
        }
    }

    private function loadZabbixUsers(): void {
        $this->userMap = [];
        $this->existingUserIds = [];
        $field = ConfigManager::getUserMatchField();
        $lowercase = ConfigManager::shouldLowercaseUsername();

        $params = [
            'output' => ['userid', 'username', 'name'],
        ];

        if ($field === 'email') {
            $params['selectMedias'] = ['sendto', 'mediatypeid'];
        }

        try {
            $users = API::User()->get($params);
        } catch (\Throwable $e) {
            error_log('IM Sync: user fetch failed: ' . $e->getMessage());
            return;
        }

        foreach ((is_array($users) ? $users : []) as $user) {
            $userid = (string) ($user['userid'] ?? '');
            if ($userid === '') {
                continue;
            }

            $this->existingUserIds[$userid] = true;

            $keys = $this->buildUserMatchKeys($user, $field, $lowercase);
            foreach ($keys as $key) {
                if ($key !== '' && !isset($this->userMap[$key])) {
                    $this->userMap[$key] = $userid;
                }
            }
        }
    }

    /**
     * 构建受管用户索引。注册表里记录的用户若已被手动删除（不在 Zabbix 中），
     * 视为陈旧记录并从注册表清除，避免后续把不存在的 userid 写入用户组。
     */
    private function buildManagedUserIndex(): void {
        $this->managedUserByImId = [];

        foreach (($this->registry['users'] ?? []) as $userid => $meta) {
            $userid = (string) $userid;
            $imUserId = (string) ($meta['im_user_id'] ?? '');

            if (!isset($this->existingUserIds[$userid])) {
                SyncRegistry::removeUser($this->registry, $userid);
                error_log('IM Sync: managed user ' . $userid
                    . ' (im_user_id=' . $imUserId . ') no longer exists in Zabbix, purged from registry.');
                continue;
            }

            if ($imUserId !== '') {
                $this->managedUserByImId[$imUserId] = $userid;
            }
        }
    }

    private function userExists(string $userid): bool {
        return $userid !== '' && isset($this->existingUserIds[$userid]);
    }

    private function buildUserMatchKeys(array $user, string $field, bool $lowercase): array {
        $keys = [];

        switch ($field) {
            case 'email':
                foreach (($user['medias'] ?? []) as $media) {
                    $sendto = trim((string) ($media['sendto'] ?? ''));
                    if ($sendto !== '') {
                        $keys[] = $this->normalizeMatchKey($sendto, $lowercase);
                    }
                }
                break;
            case 'alias':
                $keys[] = $this->normalizeMatchKey((string) ($user['name'] ?? ''), $lowercase);
                break;
            case 'username':
            default:
                $keys[] = $this->normalizeMatchKey((string) ($user['username'] ?? ''), $lowercase);
                break;
        }

        return $keys;
    }

    private function resolveOrCreateUser(
        array $imUser,
        bool $dryRun,
        int &$usersCreated,
        int &$usersUpdated,
        int &$usersMatched
    ): ?string {
        $this->lastUserCreateError = '';
        $this->lastUserWasCreated = false;
        $this->lastUserCreateApiResponse = null;
        $this->lastUserCreateParams = null;
        $this->lastCreatedPassword = '';
        $imUserId = (string) ($imUser['id'] ?? '');
        if ($imUserId !== '' && isset($this->managedUserByImId[$imUserId])) {
            $userid = $this->managedUserByImId[$imUserId];
            if (!$dryRun && ConfigManager::shouldAutoUpdateUsers() && SyncRegistry::isCreatedUser($userid)) {
                if ($this->updateManagedUser($userid, $imUser)) {
                    $usersUpdated++;
                }
            }
            $usersMatched++;
            return $userid;
        }

        $userid = $this->resolveZabbixUserId($imUser);
        if ($userid !== null) {
            $usersMatched++;
            if (!$dryRun) {
                SyncRegistry::registerUser(
                    $this->registry,
                    $userid,
                    $imUserId,
                    $this->buildZabbixUsername($imUser),
                    (string) ($imUser['name'] ?? ''),
                    'linked'
                );
                $this->managedUserByImId[$imUserId] = $userid;
                $this->registerUserInMaps($userid, $imUser);
            }
            return $userid;
        }

        if (!ConfigManager::shouldAutoCreateUsers()) {
            return null;
        }

        $username = $this->buildZabbixUsername($imUser);
        if ($username === '') {
            return null;
        }

        if ($dryRun) {
            if ($this->canAutoCreateUser($username, $imUser)) {
                $usersCreated++;
                return 'preview';
            }
            if (!ZabbixApiHelper::canCreateUsers()) {
                $this->lastUserCreateError = LanguageManager::t(
                    'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.'
                );
            }
            return null;
        }

        $createdId = $this->createManagedUser($imUser);
        if ($createdId === null) {
            return null;
        }

        if ($this->lastUserWasCreated) {
            $usersCreated++;
        } else {
            $usersMatched++;
        }

        SyncRegistry::registerUser(
            $this->registry,
            $createdId,
            $imUserId,
            $username,
            (string) ($imUser['name'] ?? ''),
            $this->lastUserWasCreated ? 'created' : 'linked'
        );
        $this->registerUserInMaps($createdId, $imUser);
        $this->managedUserByImId[$imUserId] = $createdId;

        return $createdId;
    }

    private function canAutoCreateUser(string $username, array $imUser): bool {
        if ($username === '') {
            return false;
        }
        if ($this->findExistingZabbixUser($imUser, $username) !== null) {
            return true;
        }
        if (ZabbixVersion::isVersion7() && $this->resolveDefaultRoleId() === '') {
            return false;
        }
        if (!ZabbixApiHelper::canCreateUsers()) {
            return false;
        }
        return true;
    }

    private function resolveZabbixUserId(array $imUser): ?string {
        $username = $this->buildZabbixUsername($imUser);
        return $this->findExistingZabbixUser($imUser, $username);
    }

    private function buildImUserMatchKeys(array $imUser): array {
        $lowercase = ConfigManager::shouldLowercaseUsername();
        $keys = [];
        $candidates = [
            (string) ($imUser['id'] ?? ''),
            (string) ($imUser['username'] ?? ''),
            $this->buildZabbixUsername($imUser),
        ];

        foreach ($candidates as $value) {
            $key = $this->normalizeMatchKey($value, $lowercase);
            if ($key !== '') {
                $keys[$key] = $key;
            }
        }

        return array_values($keys);
    }

    private function findZabbixUserByUsername(string $username): ?string {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $candidates = [$username];
        if (ConfigManager::shouldLowercaseUsername()) {
            $candidates[] = mb_strtolower($username);
        }

        foreach (array_unique($candidates) as $candidate) {
            try {
                $users = API::User()->get([
                    'output' => ['userid', 'username'],
                    'filter' => ['username' => $candidate],
                    'limit'  => 1,
                ]);
                if (!empty($users[0]['userid'])) {
                    $userid = (string) $users[0]['userid'];
                    $this->cacheZabbixUser($userid, (string) ($users[0]['username'] ?? $candidate));
                    return $userid;
                }
            } catch (\Throwable $e) {
                error_log('IM Sync: find user by username failed for ' . $candidate . ': ' . $e->getMessage());
            }
        }

        return null;
    }

    private function cacheZabbixUser(string $userid, string $username): void {
        $lowercase = ConfigManager::shouldLowercaseUsername();
        $key = $this->normalizeMatchKey($username, $lowercase);
        if ($key !== '') {
            $this->userMap[$key] = $userid;
        }
    }

    private function registerUserInMaps(string $userid, array $imUser): void {
        foreach ($this->buildImUserMatchKeys($imUser) as $key) {
            $this->userMap[$key] = $userid;
        }
    }

    private function normalizeMatchKey(string $value, bool $lowercase): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return $lowercase ? mb_strtolower($value) : $value;
    }

    /**
     * @return array{0:string,1:string}|null [usrgrpid, status]
     */
    private function resolveManagedGroup(
        string $groupName,
        string $imDeptId,
        int &$groupsCreated,
        int &$groupsRenamed,
        int &$groupsExisting,
        int &$groupsSkipped
    ): ?array {
        $existingManagedId = SyncRegistry::findGroupIdByDeptId($imDeptId);

        if ($existingManagedId !== null) {
            if (!isset($this->groupMembers[$existingManagedId]) && !isset($this->groupMap[$groupName])) {
                SyncRegistry::removeGroup($this->registry, $existingManagedId);
                $existingManagedId = null;
            }
        }

        if ($existingManagedId !== null) {
            $currentName = (string) ($this->registry['groups'][$existingManagedId]['name'] ?? '');
            if ($currentName !== $groupName) {
                if ($this->renameUserGroup($existingManagedId, $groupName)) {
                    unset($this->groupMap[$currentName]);
                    $this->groupMap[$groupName] = $existingManagedId;
                    SyncRegistry::registerGroup($this->registry, $existingManagedId, $groupName, $imDeptId);
                    $groupsRenamed++;
                    return [$existingManagedId, 'renamed'];
                }
                return null;
            }

            SyncRegistry::registerGroup($this->registry, $existingManagedId, $groupName, $imDeptId);
            $groupsExisting++;
            return [$existingManagedId, 'existing'];
        }

        if (isset($this->groupMap[$groupName])) {
            $groupsSkipped++;
            error_log('IM Sync: skip manual group "' . $groupName . '" (not managed by module)');
            return null;
        }

        $createdId = $this->createUserGroup($groupName);
        if ($createdId === null) {
            return null;
        }

        $this->groupMap[$groupName] = $createdId;
        $this->groupMembers[$createdId] = [];
        SyncRegistry::registerGroup($this->registry, $createdId, $groupName, $imDeptId);
        $groupsCreated++;

        return [$createdId, 'created'];
    }

    private function createUserGroup(string $name): ?string {
        $result = ZabbixApiHelper::callWrapper('UserGroup', 'create', ['name' => $name]);
        if ($result['ok'] && !empty($result['data']['usrgrpids'][0])) {
            return (string) $result['data']['usrgrpids'][0];
        }

        error_log('IM Sync: create user group failed for ' . $name . ': ' . (string) $result['error']);
        return null;
    }

    private function renameUserGroup(string $usrgrpid, string $name): bool {
        $result = ZabbixApiHelper::callWrapper('UserGroup', 'update', [
            'usrgrpid' => $usrgrpid,
            'name'     => $name,
        ]);

        if ($result['ok']) {
            return true;
        }

        error_log('IM Sync: rename user group ' . $usrgrpid . ' failed: ' . (string) $result['error']);
        return false;
    }

    /**
     * 更新模块管理用户组的成员：保留手工加入的用户，仅维护 IM 同步成员
     */
    private function updateManagedGroupUsers(string $usrgrpid, array $syncUserIds): bool {
        $this->lastGroupUpdateError = '';
        if (!SyncRegistry::isManagedGroup($usrgrpid) && !isset($this->registry['groups'][$usrgrpid])) {
            $this->lastGroupUpdateError = LanguageManager::tf('Group %s is not managed by module', $usrgrpid);
            return false;
        }

        $currentMembers = $this->groupMembers[$usrgrpid] ?? [];
        $manualMembers = [];

        foreach ($currentMembers as $userid) {
            if (!isset($this->registry['users'][$userid])) {
                $manualMembers[] = $userid;
            }
        }

        $finalMembers = array_values(array_unique(array_merge($manualMembers, $syncUserIds)));
        sort($finalMembers);

        $users = [];
        $validMembers = [];
        foreach ($finalMembers as $userid) {
            $userid = (string) $userid;
            if ($userid === 'preview' || $userid === '') {
                continue;
            }
            // 兜底：跳过 Zabbix 中已不存在的用户（例如被手动删除），避免整组更新失败
            if (!empty($this->existingUserIds) && !isset($this->existingUserIds[$userid])) {
                error_log('IM Sync: skip non-existent user ' . $userid . ' for group ' . $usrgrpid);
                continue;
            }
            $users[] = ['userid' => $userid];
            $validMembers[] = $userid;
        }
        $finalMembers = $validMembers;

        $result = ZabbixApiHelper::callWrapper('UserGroup', 'update', [
            'usrgrpid' => $usrgrpid,
            'users'    => $users,
        ]);

        if ($result['ok']) {
            $this->groupMembers[$usrgrpid] = $finalMembers;
            return true;
        }

        $this->lastGroupUpdateError = (string) $result['error'];
        error_log('IM Sync: update user group ' . $usrgrpid . ' failed: ' . (string) $result['error']);
        return false;
    }

    private function createManagedUser(array $imUser): ?string {
        $this->lastUserCreateError = '';
        $this->lastUserWasCreated = false;
        $this->lastUserCreateApiResponse = null;
        $this->lastUserCreateParams = null;
        $this->lastCreatedPassword = '';
        $username = $this->buildZabbixUsername($imUser);
        $displayName = (string) ($imUser['name'] ?? $username);
        $password = $this->resolveUserPassword($username, $displayName);
        if ($username === '' || $password === '') {
            $this->lastUserCreateError = LanguageManager::t('Username or password is empty.');
            return null;
        }

        $existing = $this->findExistingZabbixUser($imUser, $username);
        if ($existing !== null) {
            return $existing;
        }

        $params = [
            'username' => $username,
            'passwd'   => $password,
            'name'     => $displayName,
        ];

        if (ZabbixVersion::isVersion7()) {
            $roleid = $this->resolveDefaultRoleId();
            if ($roleid === '') {
                $this->lastUserCreateError = LanguageManager::t(
                    'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.'
                );
                return null;
            }
            $params['roleid'] = $roleid;
        } else {
            $params['type'] = ConfigManager::getDefaultUserType();
        }

        $this->lastUserCreateParams = $params;

        $apiResult = ZabbixApiHelper::createUser($params);
        $this->lastUserCreateApiResponse = $apiResult;

        if ($apiResult['ok']) {
            $userid = $this->extractCreatedUserId($apiResult['data']);
            if ($userid !== null) {
                $this->lastUserWasCreated = true;
                $this->lastCreatedPassword = $password;
                $this->existingUserIds[$userid] = true;
                $this->cacheZabbixUser($userid, $username);
                $email = trim((string) ($imUser['email'] ?? ''));
                if ($email !== '') {
                    $this->updateUserEmail($userid, $email);
                }
                return $userid;
            }
            $this->lastUserCreateError = LanguageManager::tf(
                'API returned unexpected result: %s',
                json_encode($apiResult['data'], JSON_UNESCAPED_UNICODE)
            );
        } else {
            $this->lastUserCreateError = (string) ($apiResult['error'] ?? LanguageManager::t('Unknown API error'));
            error_log('IM Sync: create user failed for ' . $username . ': ' . $this->lastUserCreateError);

            $existing = $this->findExistingZabbixUser($imUser, $username);
            if ($existing !== null) {
                return $existing;
            }
        }

        return null;
    }

    private function findExistingZabbixUser(array $imUser, string $username): ?string {
        foreach ($this->buildImUserMatchKeys($imUser) as $key) {
            if (isset($this->userMap[$key])) {
                return $this->userMap[$key];
            }
        }

        $found = $this->findZabbixUserByUsername($username);
        if ($found !== null) {
            return $found;
        }

        $rawId = trim((string) ($imUser['id'] ?? ''));
        if ($rawId !== '' && $rawId !== $username) {
            return $this->findZabbixUserByUsername($rawId);
        }

        return null;
    }

    /**
     * @return array{usrgrpid:?string,ready:bool}
     */
    private function resolveUserSyncGroup(string $groupName, string $imDeptId, bool $dryRun): array {
        unset($groupName, $dryRun);
        $usrgrpid = SyncRegistry::findGroupIdByDeptId($imDeptId);
        if ($usrgrpid !== null && isset($this->registry['groups'][$usrgrpid])) {
            return ['usrgrpid' => $usrgrpid, 'ready' => true];
        }

        return ['usrgrpid' => null, 'ready' => false];
    }

    private function updateUserEmail(string $userid, string $email): void {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $media = $this->buildEmailMediaPayload($email);
        if ($media === null) {
            return;
        }

        try {
            API::User()->update([
                'userid' => $userid,
                'medias' => [$media],
            ]);
        } catch (\Throwable $e) {
            error_log('IM Sync: update user email failed for ' . $userid . ': ' . $e->getMessage());
        }
    }

    private function buildEmailMediaPayload(string $email): ?array {
        if (ZabbixVersion::isVersion7()) {
            return [
                'mediatypeid' => '1',
                'sendto'      => [$email],
                'active'      => 0,
                'severity'    => 63,
                'provider'    => 0,
            ];
        }

        return [
            'mediatypeid' => 1,
            'sendto'      => $email,
            'active'      => 1,
            'severity'    => 63,
        ];
    }

    private function updateManagedUser(string $userid, array $imUser): bool {
        if (!SyncRegistry::isCreatedUser($userid)) {
            return false;
        }

        $params = [
            'userid' => $userid,
            'name'   => (string) ($imUser['name'] ?? ''),
        ];

        $email = trim((string) ($imUser['email'] ?? ''));
        if ($email !== '') {
            $media = $this->buildEmailMediaPayload($email);
            if ($media !== null) {
                $params['medias'] = [$media];
            }
        }

        try {
            API::User()->update($params);
            $origin = SyncRegistry::isCreatedUser($userid) ? 'created' : 'linked';
            SyncRegistry::registerUser(
                $this->registry,
                $userid,
                (string) ($imUser['id'] ?? ''),
                (string) ($imUser['username'] ?? ($imUser['id'] ?? '')),
                (string) ($imUser['name'] ?? ''),
                $origin
            );
            return true;
        } catch (\Throwable $e) {
            error_log('IM Sync: update user ' . $userid . ' failed: ' . $e->getMessage());
            return false;
        }
    }

    private function buildZabbixUsername(array $imUser): string {
        $field = ConfigManager::getUserMatchField();
        switch ($field) {
            case 'email':
                $value = (string) ($imUser['email'] ?? '');
                if ($value !== '' && strpos($value, '@') !== false) {
                    $value = substr($value, 0, (int) strpos($value, '@'));
                }
                break;
            case 'alias':
                $value = (string) ($imUser['name'] ?? '');
                break;
            case 'username':
            default:
                $value = (string) ($imUser['username'] ?? ($imUser['id'] ?? ''));
                break;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (ConfigManager::shouldLowercaseUsername()) {
            $value = mb_strtolower($value);
        }

        return $this->sanitizeZabbixUsername($value);
    }

    private function sanitizeZabbixUsername(string $value): string {
        $value = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $value) ?? '';
        $value = trim($value, '._-');
        if ($value === '') {
            return '';
        }
        return substr($value, 0, 100);
    }

    private function resolveDefaultPassword(string $username): string {
        return $this->resolveUserPassword($username, '');
    }

    /**
     * 新同步用户统一使用 12 位大小写字母+数字随机密码。
     */
    private function resolveUserPassword(string $username, string $name): string {
        return $this->generateSyncPassword($username, $name);
    }

    private function passwordContainsIdentity(string $password, string $username, string $name): bool {
        $haystack = mb_strtolower($password);
        foreach ([$username, $name] as $identity) {
            $identity = trim(mb_strtolower($identity));
            if ($identity !== '' && mb_strpos($haystack, $identity) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成 12 位大小写字母+数字密码，且不包含用户名/姓名子串。
     */
    private function generateSyncPassword(string $username, string $name): string {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digit = '23456789';
        $pool = $upper . $lower . $digit;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $chars = [
                $upper[random_int(0, strlen($upper) - 1)],
                $lower[random_int(0, strlen($lower) - 1)],
                $digit[random_int(0, strlen($digit) - 1)],
            ];

            while (count($chars) < 12) {
                $chars[] = $pool[random_int(0, strlen($pool) - 1)];
            }

            shuffle($chars);
            $password = implode('', $chars);

            if (!$this->passwordContainsIdentity($password, $username, $name)) {
                return $password;
            }
        }

        return 'Zx' . random_int(100000, 999999) . 'Ab';
    }

    private function resolveDefaultRoleId(): string {
        $roleid = ConfigManager::getDefaultRoleId();
        if ($roleid !== '') {
            return $roleid;
        }

        if (!ZabbixVersion::isVersion7()) {
            return '';
        }

        try {
            $roles = API::Role()->get([
                'output'    => ['roleid', 'name'],
                'sortfield' => 'name',
            ]);
        } catch (\Throwable $e) {
            error_log('IM Sync: role fetch failed: ' . $e->getMessage());
            return '';
        }

        foreach ((is_array($roles) ? $roles : []) as $role) {
            $name = strtolower((string) ($role['name'] ?? ''));
            if ($name === 'user role' || $name === 'user' || strpos($name, 'user') !== false) {
                if (strpos($name, 'admin') === false && strpos($name, 'super') === false) {
                    return (string) ($role['roleid'] ?? '');
                }
            }
        }

        if (!empty($roles[0]['roleid'])) {
            return (string) $roles[0]['roleid'];
        }

        return '';
    }

    private function removeOrphanManagedGroupsByDeptIds(array $activeDeptIds): int {
        $active = array_fill_keys($activeDeptIds, true);
        $deleted = 0;

        foreach (($this->registry['groups'] ?? []) as $usrgrpid => $meta) {
            $imDeptId = (string) ($meta['im_dept_id'] ?? '');
            if ($imDeptId === '' || isset($active[$imDeptId])) {
                continue;
            }

            $name = (string) ($meta['name'] ?? '');

            try {
                API::UserGroup()->delete([(string) $usrgrpid]);
                SyncRegistry::removeGroup($this->registry, (string) $usrgrpid);
                if ($name !== '') {
                    unset($this->groupMap[$name]);
                }
                unset($this->groupMembers[(string) $usrgrpid]);
                $deleted++;
            } catch (\Throwable $e) {
                error_log('IM Sync: delete managed group ' . $name . ' failed: ' . $e->getMessage());
            }
        }

        return $deleted;
    }

    private function removeOrphanManagedUsers(array $activeImUserIds): int {
        $active = array_fill_keys($activeImUserIds, true);
        $deleted = 0;

        foreach (($this->registry['users'] ?? []) as $userid => $meta) {
            if ((string) $userid === '1') {
                continue;
            }
            if (!SyncRegistry::isCreatedUser((string) $userid)) {
                continue;
            }

            $imUserId = (string) ($meta['im_user_id'] ?? '');
            if ($imUserId === '' || isset($active[$imUserId])) {
                continue;
            }

            try {
                API::User()->delete([(string) $userid]);
                SyncRegistry::removeUser($this->registry, (string) $userid);
                unset($this->managedUserByImId[$imUserId]);
                $deleted++;
            } catch (\Throwable $e) {
                error_log('IM Sync: delete managed user ' . $userid . ' failed: ' . $e->getMessage());
            }
        }

        return $deleted;
    }

    /** 清理 IM 中已不存在的关联用户（仅移除注册记录，不删除账号） */
    private function cleanupLinkedUsers(array $activeImUserIds): void {
        $active = array_fill_keys($activeImUserIds, true);

        foreach (($this->registry['users'] ?? []) as $userid => $meta) {
            if (SyncRegistry::isCreatedUser((string) $userid)) {
                continue;
            }

            $imUserId = (string) ($meta['im_user_id'] ?? '');
            if ($imUserId === '' || isset($active[$imUserId])) {
                continue;
            }

            SyncRegistry::removeUser($this->registry, (string) $userid);
            unset($this->managedUserByImId[$imUserId]);
        }
    }

    private function formatImUserLabel(array $imUser): string {
        $name = trim((string) ($imUser['name'] ?? ''));
        $username = trim((string) ($imUser['username'] ?? ($imUser['id'] ?? '')));
        if ($name !== '' && $username !== '' && $name !== $username) {
            return $name . ' (' . $username . ')';
        }
        return $name !== '' ? $name : $username;
    }

    private function recordUserSyncListEntry(
        array &$usersSyncList,
        array $imUser,
        string $departmentName,
        ?string $userid,
        bool $dryRun
    ): void {
        $imUserId = (string) ($imUser['id'] ?? '');
        if ($imUserId === '') {
            return;
        }

        if (!isset($usersSyncList[$imUserId])) {
            $usersSyncList[$imUserId] = [
                'username'   => $this->buildZabbixUsername($imUser),
                'name'       => (string) ($imUser['name'] ?? ''),
                'mobile'     => (string) ($imUser['mobile'] ?? ''),
                'password'   => '',
                'status'     => 'unmatched',
                'department' => $departmentName,
                'error'      => '',
            ];
        } elseif ($departmentName !== '' && $usersSyncList[$imUserId]['department'] !== $departmentName) {
            $usersSyncList[$imUserId]['department'] .= ', ' . $departmentName;
        }

        if ($userid === null) {
            if ($usersSyncList[$imUserId]['status'] !== 'created') {
                $usersSyncList[$imUserId]['status'] = 'unmatched';
                $usersSyncList[$imUserId]['error'] = $this->lastUserCreateError;
            }
            return;
        }

        if ($userid === 'preview') {
            if ($usersSyncList[$imUserId]['status'] !== 'created') {
                $usersSyncList[$imUserId]['status'] = 'preview';
            }
            return;
        }

        if ($this->lastUserWasCreated && !$dryRun) {
            $usersSyncList[$imUserId]['status'] = 'created';
            $usersSyncList[$imUserId]['password'] = $this->lastCreatedPassword;
            $usersSyncList[$imUserId]['error'] = '';
            return;
        }

        if ($usersSyncList[$imUserId]['status'] !== 'created') {
            $usersSyncList[$imUserId]['status'] = 'matched';
            $usersSyncList[$imUserId]['error'] = '';
        }
    }

    private function recordImUserDebug(
        array &$imUsersRaw,
        array $imUser,
        string $departmentName,
        ?string $userid,
        bool $dryRun
    ): void {
        $imUserId = (string) ($imUser['id'] ?? '');
        if ($imUserId === '') {
            return;
        }

        $status = 'matched';
        if ($userid === null) {
            $status = 'unmatched';
        } elseif ($userid === 'preview') {
            $status = 'preview';
        } elseif ($this->lastUserWasCreated) {
            $status = 'created';
        }

        $createParams = $this->lastUserCreateParams;
        if (is_array($createParams) && array_key_exists('passwd', $createParams)) {
            $createParams['passwd'] = '***';
        }

        $imUsersRaw[$imUserId] = [
            'im_user_id'      => $imUserId,
            'name'            => (string) ($imUser['name'] ?? ''),
            'username'        => (string) ($imUser['username'] ?? ''),
            'email'           => (string) ($imUser['email'] ?? ''),
            'mobile'          => (string) ($imUser['mobile'] ?? ''),
            'zabbix_username' => $this->buildZabbixUsername($imUser),
            'department'      => $departmentName,
            'status'          => $status,
            'error'           => $status === 'unmatched' ? $this->lastUserCreateError : '',
            'raw'             => $imUser['raw'] ?? $imUser,
            'zabbix_create'   => $status === 'unmatched' ? [
                'params'   => $createParams,
                'response' => $this->lastUserCreateApiResponse,
                'hint'     => ZabbixApiHelper::canCreateUsers()
                    ? ''
                    : LanguageManager::t('Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.'),
            ] : null,
        ];
    }

    /**
     * @param mixed $result
     */
    private function extractCreatedUserId($result): ?string {
        if ($result === false || $result === null) {
            return null;
        }

        if (!is_array($result)) {
            return null;
        }

        if (!empty($result['userids'][0])) {
            return (string) $result['userids'][0];
        }

        if (!empty($result['userid'])) {
            return (string) $result['userid'];
        }

        if (!empty($result['ids'][0])) {
            return (string) $result['ids'][0];
        }

        foreach ($result as $value) {
            if (is_array($value) && !empty($value[0]) && is_scalar($value[0])) {
                return (string) $value[0];
            }
        }

        return null;
    }
}
