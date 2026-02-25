<?php
/**
 * 主机告警查询控制器
 * 返回指定主机的活跃告警信息
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use API;

require_once dirname(__DIR__) . '/lib/HostRackManager.php';
use Modules\ZabbixRack\Lib\HostRackManager;

class HostProblems extends CController {
    
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
        
        return $this->validateInput($fields);
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $hostId = $this->getInput('hostid', '');
        
        $response = ['success' => false, 'problems' => []];
        
        try {
            $problems = HostRackManager::getHostProblemDetails($hostId);
            
            $response['success'] = true;
            $response['problems'] = $problems;
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response);
        exit;
    }
}
