<?php

namespace Modules\ZabbixIm\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;

class ImSettingsEnable extends CController {

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

        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            $this->respond(false, LanguageManager::t('Invalid input parameters.'));
            return;
        }

        $enable = !isset($_POST['enable']) || ($_POST['enable'] !== '0' && $_POST['enable'] !== 'false');

        try {
            if ($enable) {
                $setting = ConfigManager::findSetting($id);
                if ($setting === null) {
                    $this->respond(false, LanguageManager::t('Sync setting not found'));
                    return;
                }
                if (!ConfigManager::isSettingConfigured($setting)) {
                    $this->respond(false, LanguageManager::t('This sync setting is missing required credentials.'));
                    return;
                }
                $ok = ConfigManager::enableSetting($id);
            } else {
                $ok = ConfigManager::disableSetting($id);
            }

            if ($ok) {
                $this->respond(true, LanguageManager::t('Settings saved'));
            } else {
                $this->respond(false, LanguageManager::t('Sync setting not found'));
            }
        } catch (\Throwable $e) {
            error_log('IM Settings enable error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Save failed') . ': ' . $e->getMessage());
        }
    }

    private function respond(bool $ok, string $message): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
