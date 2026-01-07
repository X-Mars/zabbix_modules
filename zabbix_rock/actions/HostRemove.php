<?php
/**
 * 从机柜移除主机控制器
 */

namespace Modules\ZabbixRock\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\HostRackManager;

class HostRemove extends CController {
    
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
            'hostid' => 'required|string'
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
        $hostId = $this->getInput('hostid');
        
        $success = HostRackManager::removeHost($hostId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? LanguageManager::t('remove_success') : LanguageManager::t('remove_failed')
        ]);
        exit;
    }
}
