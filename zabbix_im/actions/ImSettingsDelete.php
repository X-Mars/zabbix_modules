<?php

namespace Modules\ZabbixIm\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;

class ImSettingsDelete extends CController {

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

        try {
            $ok = ConfigManager::deleteSetting($id);
            if ($ok) {
                $this->respond(true, LanguageManager::t('Settings deleted'));
            } else {
                $this->respond(false, LanguageManager::t('Sync setting not found'));
            }
        } catch (\Throwable $e) {
            error_log('IM Settings delete error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Delete failed') . ': ' . $e->getMessage());
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
