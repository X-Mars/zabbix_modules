<?php

namespace Modules\ZabbixIm\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/SyncRegistry.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;
use Modules\ZabbixIm\Lib\SyncRegistry;

class Im extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $config = ConfigManager::load();

        $response = new CControllerResponseData([
            'title'              => LanguageManager::t('IM Sync'),
            'is_configured'      => ConfigManager::isConfigured(),
            'has_active_setting' => ConfigManager::getActiveSetting() !== null,
            'active_setting_name'=> ConfigManager::getActiveSettingName(),
            'provider'           => ConfigManager::getProvider(),
            'provider_label'     => ConfigManager::getProviderLabel(),
            'use_full_path'      => ConfigManager::useFullPath(),
            'remove_orphans'     => ConfigManager::shouldRemoveOrphans(),
            'remove_orphan_users'=> ConfigManager::shouldRemoveOrphanUsers(),
            'auto_create_users'  => ConfigManager::shouldAutoCreateUsers(),
            'config_path'        => ConfigManager::getConfigPath(),
            'registry_path'      => ConfigManager::getRegistryPath(),
            'managed_groups'     => SyncRegistry::getManagedGroupCount(),
            'managed_users'      => SyncRegistry::getManagedUserCount(),
            'user_groups'        => $this->getUserGroups(),
        ]);

        $response->setTitle(LanguageManager::t('IM Sync'));
        $this->setResponse($response);
    }

    private function getUserGroups(): array {
        $registry = SyncRegistry::load();
        $managedIds = array_keys($registry['groups'] ?? []);
        if (empty($managedIds)) {
            return [];
        }

        try {
            $groups = API::UserGroup()->get([
                'output'      => ['usrgrpid', 'name'],
                'usrgrpids'   => $managedIds,
                'selectUsers' => ['userid', 'username'],
                'sortfield'   => 'name',
                'sortorder'   => 'ASC',
            ]);
        } catch (\Throwable $e) {
            error_log('IM Sync: user group fetch failed: ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ((is_array($groups) ? $groups : []) as $group) {
            $users = $group['users'] ?? [];
            $result[] = [
                'usrgrpid'   => (string) ($group['usrgrpid'] ?? ''),
                'name'       => (string) ($group['name'] ?? ''),
                'user_count' => is_array($users) ? count($users) : 0,
                'managed'    => true,
            ];
        }

        return $result;
    }
}
