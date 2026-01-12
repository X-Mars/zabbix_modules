<?php
/**
 * 保存机柜控制器
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;

class RackSave extends CController {
    
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
            'room_id' => 'required|string',
            'height' => 'int32',
            'description' => 'string'
        ];
        
        $ret = $this->validateInput($fields);
        
        if (!$ret) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid input'
            ]);
            exit;
        }
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $rack = [
            'id' => $this->getInput('id', ''),
            'name' => $this->getInput('name'),
            'room_id' => $this->getInput('room_id'),
            'height' => $this->getInput('height', 42),
            'description' => $this->getInput('description', '')
        ];
        
        $success = RackConfig::saveRack($rack);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? LanguageManager::t('save_success') : LanguageManager::t('save_failed')
        ]);
        exit;
    }
}
