<?php
/**
 * 机柜视图控制器
 * 显示机柜可视化界面
 */

namespace Modules\ZabbixRock\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\RackConfig;
use Modules\ZabbixRock\Lib\HostRackManager;
use Modules\ZabbixRock\Lib\ViewRenderer;

class RackView extends CController {
    
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
            'room_id' => 'string',
            'rack_id' => 'string',
            'search' => 'string'
        ];
        
        $ret = $this->validateInput($fields);
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $roomId = $this->getInput('room_id', '');
        $rackId = $this->getInput('rack_id', '');
        $search = $this->getInput('search', '');
        
        // 获取所有机房
        $rooms = RackConfig::getRooms();
        
        // 获取机柜列表
        $racks = [];
        if ($roomId) {
            $racks = RackConfig::getRacks($roomId);
        } elseif (!empty($rooms)) {
            // 默认选择第一个机房
            $roomId = $rooms[0]['id'];
            $racks = RackConfig::getRacks($roomId);
        }
        
        // 获取当前选中的机柜
        $currentRack = null;
        $hosts = [];
        
        if ($rackId) {
            $currentRack = RackConfig::getRack($rackId);
        } elseif (!empty($racks)) {
            // 默认选择第一个机柜
            $currentRack = $racks[0];
            $rackId = $currentRack['id'];
        }
        
        // 获取机柜中的主机
        if ($currentRack) {
            $roomInfo = RackConfig::getRoom($currentRack['room_id']);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            $hosts = HostRackManager::getHostsInRack($roomName, $currentRack['name']);
            
            // 获取主机告警数据
            if (!empty($hosts)) {
                $hostIds = array_column($hosts, 'hostid');
                $hostProblems = HostRackManager::getHostProblems($hostIds);
                
                // 将告警数据合并到主机数据中
                foreach ($hosts as &$host) {
                    $hostId = $host['hostid'];
                    if (isset($hostProblems[$hostId])) {
                        $host['problem_count'] = $hostProblems[$hostId]['count'];
                        $host['problems'] = $hostProblems[$hostId]['problems'];
                    } else {
                        $host['problem_count'] = 0;
                        $host['problems'] = [];
                    }
                }
                unset($host);
            }
        }
        
        // 如果有搜索关键字，搜索主机
        $searchResults = [];
        if ($search) {
            $searchResults = HostRackManager::searchAssignedHosts($search);
        }
        
        // 获取主机组列表（用于添加主机弹窗）
        $hostGroups = HostRackManager::getHostGroups();
        
        $data = [
            'rooms' => $rooms,
            'racks' => $racks,
            'current_room_id' => $roomId,
            'current_rack_id' => $rackId,
            'current_rack' => $currentRack,
            'hosts' => $hosts,
            'search' => $search,
            'search_results' => $searchResults,
            'host_groups' => $hostGroups,
            'lang' => LanguageManager::class
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_view'));
        
        $this->setResponse($response);
    }
}
