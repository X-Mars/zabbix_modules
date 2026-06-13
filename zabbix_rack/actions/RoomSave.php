<?php
/**
 * 保存机房控制器
 */

namespace Modules\ZabbixRack\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/RackPermission.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\RackPermission;

class RoomSave extends CController {
    
    protected function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        $fields = [
            'id' => 'string',
            'name' => 'required|string',
            'description' => 'string',
            'user_groups' => 'string',
            'users' => 'string',
        ];
        
        $ret = $this->validateInput($fields);
        
        if (!$ret) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('invalid_input')
            ]);
            exit;
        }
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return RackPermission::canAccessManage();
    }
    
    protected function doAction(): void {
        $allPermissionIds = RackPermission::getAllPermissionOptionIds();
        $room = [
            'id' => $this->getInput('id', ''),
            'name' => $this->getInput('name'),
            'description' => $this->getInput('description', ''),
            'user_groups' => RackPermission::normalizeIdList(
                $this->parseJsonIdList($this->getInput('user_groups', ''))
            ),
            'users' => RackPermission::normalizeIdList(
                $this->parseJsonIdList($this->getInput('users', ''))
            ),
        ];
        $room = RackPermission::finalizeRoomPermissions(
            $room,
            $allPermissionIds['user_groups'],
            $allPermissionIds['users']
        );
        
        $success = RackConfig::saveRoom($room);
        
        $message = LanguageManager::t('save_success');
        if (!$success) {
            if (!RackConfig::isDataDirWritable()) {
                $message = str_replace('{path}', RackConfig::getDataDir(), LanguageManager::t('save_permission_hint'));
            } else {
                $message = LanguageManager::t('save_failed');
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * @param mixed $input
     * @return array<int, string>
     */
    private function parseJsonIdList($input): array {
        if (is_array($input)) {
            return $input;
        }

        $input = trim((string) $input);
        if ($input === '') {
            return [];
        }

        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return preg_split('/\s*,\s*/', $input) ?: [];
    }
}
