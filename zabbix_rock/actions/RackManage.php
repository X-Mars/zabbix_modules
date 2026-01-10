<?php
/**
 * 机柜管理控制器
 * 管理机房和机柜的配置
 */

namespace Modules\ZabbixRock\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\RackConfig;
use Modules\ZabbixRock\Lib\HostRackManager;

class RackManage extends CController {
    
    protected function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        return true;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        // 获取所有机房
        $rooms = RackConfig::getRooms();
        
        // 获取所有机柜
        $racks = RackConfig::getRacks();
        
        // 为机柜添加机房名称
        $roomNames = [];
        foreach ($rooms as $room) {
            $roomNames[$room['id']] = $room['name'];
        }
        
        // 计算每个机柜的已使用 U 位
        foreach ($racks as &$rack) {
            $rack['room_name'] = $roomNames[$rack['room_id']] ?? '';
            
            // 获取机柜中的主机来计算已使用 U 位
            $usedU = 0;
            if (!empty($rack['room_name']) && !empty($rack['name'])) {
                try {
                    $hosts = HostRackManager::getHostsInRack($rack['room_name'], $rack['name']);
                    foreach ($hosts as $host) {
                        if (isset($host['u_height']) && $host['u_height'] > 0) {
                            $usedU += $host['u_height'];
                        }
                    }
                } catch (\Exception $e) {
                    // 忽略错误，使用默认值
                }
            }
            $rack['used_u'] = $usedU;
        }
        
        $data = [
            'rooms' => $rooms,
            'racks' => $racks,
            'lang' => LanguageManager::class
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_manage'));
        
        $this->setResponse($response);
    }
}
