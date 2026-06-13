<?php

namespace Modules\ZabbixIm\Actions;

use CController,
    CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;

class ImSettings extends CController {

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
        return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $active = ConfigManager::getActiveSetting();
        $activeId = $active !== null ? (string) $active['id'] : '';

        $settings = [];
        foreach (ConfigManager::getSettings() as $setting) {
            $settings[] = [
                'id'                 => (string) $setting['id'],
                'name'               => (string) $setting['name'],
                'provider'           => (string) $setting['provider'],
                'provider_label'     => ConfigManager::providerLabelFor((string) $setting['provider']),
                'enabled'            => !empty($setting['enabled']),
                'configured'         => ConfigManager::isSettingConfigured($setting),
                'root_department_id' => (string) $setting['root_department_id'],
                'corp_id'            => (string) $setting['corp_id'],
                'app_id'             => (string) $setting['app_id'],
                'app_key'            => (string) $setting['app_key'],
                // 机密字段不下发，仅告知是否已设置。
                'corp_secret_set'    => trim((string) $setting['corp_secret']) !== '',
                'app_secret_set'     => trim((string) $setting['app_secret']) !== '',
            ];
        }

        $response = new CControllerResponseData([
            'title'           => LanguageManager::t('IM Sync Settings'),
            'settings'        => $settings,
            'active_id'       => $activeId,
            'config_path'     => ConfigManager::getConfigPath(),
            'provider_fields' => ConfigManager::PROVIDER_FIELDS,
        ]);

        $response->setTitle(LanguageManager::t('IM Sync Settings'));
        $this->setResponse($response);
    }
}
