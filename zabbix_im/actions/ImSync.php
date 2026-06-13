<?php

namespace Modules\ZabbixIm\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/SyncRegistry.php';
require_once dirname(__DIR__) . '/lib/ZabbixVersion.php';
require_once dirname(__DIR__) . '/lib/ImSyncService.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;
use Modules\ZabbixIm\Lib\ImSyncService;

class ImSync extends CController {

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
        if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
            $this->respond(false, LanguageManager::t('No permission.'));
            return;
        }

        if (!ConfigManager::isConfigured()) {
            $this->respond(false, LanguageManager::t('IM provider is not configured. Please configure it in IM Sync Settings.'));
            return;
        }

        try {
            $service = new ImSyncService(ConfigManager::getRuntimeConfig());
            $summary = $service->syncGroups(false);
            $this->respond(true, LanguageManager::t('Sync completed'), $summary);
        } catch (\Throwable $e) {
            error_log('IM Sync error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Sync failed') . ': ' . $e->getMessage());
        }
    }

    private function respond(bool $ok, string $message, array $summary = []): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
