<?php
/**
 * 分配主机到机柜控制器
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\HostRackManager;

class HostAssign extends CController {
    
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
            'hostid' => 'required|string',
            'room_id' => 'required|string',
            'rack_id' => 'required|string',
            'u_start' => 'required|int32',
            'u_end' => 'required|int32'
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
        $roomId = $this->getInput('room_id');
        $rackId = $this->getInput('rack_id');
        $uStart = $this->getInput('u_start');
        $uEnd = $this->getInput('u_end');
        
        // 获取机房和机柜信息
        $room = RackConfig::getRoom($roomId);
        $rack = RackConfig::getRack($rackId);
        
        if (!$room || !$rack) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('rack_not_found')
            ]);
            exit;
        }
        
        // 验证U位范围
        if ($uStart < 1 || $uEnd < 1 || $uStart > $rack['height'] || $uEnd > $rack['height'] || $uStart > $uEnd) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('invalid_position')
            ]);
            exit;
        }
        
        // 检查U位是否可用
        if (!HostRackManager::isPositionAvailable($room['name'], $rack['name'], $uStart, $uEnd)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('position_occupied')
            ]);
            exit;
        }
        
        // 分配主机
        $success = HostRackManager::assignHost($hostId, $room['name'], $rack['name'], $uStart, $uEnd);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? LanguageManager::t('assign_success') : LanguageManager::t('assign_failed')
        ]);
        exit;
    }
}
