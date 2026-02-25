<?php
/**
 * 保存机房控制器
 */

namespace Modules\ZabbixRack\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;

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
            'description' => 'string'
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
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $room = [
            'id' => $this->getInput('id', ''),
            'name' => $this->getInput('name'),
            'description' => $this->getInput('description', '')
        ];
        
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
}
