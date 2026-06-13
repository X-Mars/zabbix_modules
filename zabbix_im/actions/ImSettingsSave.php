<?php

namespace Modules\ZabbixIm\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;

class ImSettingsSave extends CController {

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
        if ($this->getUserType() < USER_TYPE_SUPER_ADMIN) {
            $this->respond(false, LanguageManager::t('No permission.'));
            return;
        }

        $provider = strtolower(trim((string) ($_POST['provider'] ?? '')));
        if (!in_array($provider, ConfigManager::PROVIDERS, true)) {
            $this->respond(false, LanguageManager::t('Invalid input parameters.'));
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $name = ConfigManager::providerLabelFor($provider);
        }

        $input = [
            'id'                 => (string) ($_POST['id'] ?? ''),
            'name'               => $name,
            'provider'           => $provider,
            'enabled'            => !empty($_POST['enabled']) && $_POST['enabled'] !== '0' && $_POST['enabled'] !== 'false',
            'root_department_id' => trim((string) ($_POST['root_department_id'] ?? '')),
            'corp_id'            => trim((string) ($_POST['corp_id'] ?? '')),
            'corp_secret'        => (string) ($_POST['corp_secret'] ?? ''),
            'app_id'             => trim((string) ($_POST['app_id'] ?? '')),
            'app_secret'         => (string) ($_POST['app_secret'] ?? ''),
            'app_key'            => trim((string) ($_POST['app_key'] ?? '')),
        ];

        try {
            $setting = ConfigManager::saveSetting($input);
            $this->respond(true, LanguageManager::t('Settings saved'), (string) $setting['id']);
        } catch (\Throwable $e) {
            error_log('IM Settings save error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Save failed') . ': ' . $e->getMessage());
        }
    }

    private function respond(bool $ok, string $message, string $id = ''): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'id'      => $id,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
