<?php
/**
 * 机柜告警批量刷新控制器
 * 批量返回指定主机列表的当前活跃告警状态
 * 用于前端定时轮询刷新告警状态
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use API;

require_once dirname(__DIR__) . '/lib/HostRackManager.php';
use Modules\ZabbixRack\Lib\HostRackManager;

class RackProblemsRefresh extends CController {
    
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
            'hostids' => 'string'
        ];
        
        return $this->validateInput($fields);
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $hostIdsStr = $this->getInput('hostids', '');
        
        $response = ['success' => false, 'data' => []];
        
        try {
            $hostIds = array_filter(array_map('trim', explode(',', $hostIdsStr)));
            
            if (empty($hostIds)) {
                $response['success'] = true;
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode($response);
                exit;
            }
            
            $hostProblems = HostRackManager::getHostProblems($hostIds);
            
            $data = [];
            foreach ($hostIds as $hostId) {
                if (isset($hostProblems[$hostId])) {
                    $problems = $hostProblems[$hostId]['problems'];
                    $maxSeverity = -1;
                    foreach ($problems as $problem) {
                        if ((int)$problem['severity'] > $maxSeverity) {
                            $maxSeverity = (int)$problem['severity'];
                        }
                    }
                    $data[$hostId] = [
                        'problem_count' => $hostProblems[$hostId]['count'],
                        'max_severity' => $maxSeverity
                    ];
                } else {
                    $data[$hostId] = [
                        'problem_count' => 0,
                        'max_severity' => -1
                    ];
                }
            }
            
            $response['success'] = true;
            $response['data'] = $data;
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response);
        exit;
    }
}
