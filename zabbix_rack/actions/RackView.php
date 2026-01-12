<?php
/**
 * 机柜视图控制器
 * 显示机柜可视化界面
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\HostRackManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

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
        
        // 新增：所有机柜的数据（用于多机柜展示）
        $allRacksData = [];
        $showOverview = false; // 是否展示机柜概览模式
        
        if ($rackId) {
            // 用户选择了特定机柜，展示单机柜详情
            $currentRack = RackConfig::getRack($rackId);
            $showOverview = false;
        } else {
            // 没有选择机柜，展示当前机房所有机柜概览
            $showOverview = true;
        }
        
        // 获取机柜中的主机 - 单机柜模式
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
                        // 获取最高严重程度
                        $host['max_severity'] = 0;
                        foreach ($hostProblems[$hostId]['problems'] as $problem) {
                            if ($problem['severity'] > $host['max_severity']) {
                                $host['max_severity'] = $problem['severity'];
                            }
                        }
                    } else {
                        $host['problem_count'] = 0;
                        $host['problems'] = [];
                        $host['max_severity'] = -1; // 无告警
                    }
                }
                unset($host);
            }
        }
        
        // 获取所有机柜的数据 - 概览模式
        if ($showOverview && !empty($racks)) {
            $roomInfo = RackConfig::getRoom($roomId);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            
            foreach ($racks as $rack) {
                $rackHosts = HostRackManager::getHostsInRack($roomName, $rack['name']);
                $rackProblems = 0;
                $maxSeverity = -1; // -1 表示无告警
                $hostCount = count($rackHosts);
                $usedU = 0;
                
                // 获取主机告警
                if (!empty($rackHosts)) {
                    $hostIds = array_column($rackHosts, 'hostid');
                    $hostProblemsData = HostRackManager::getHostProblems($hostIds);
                    
                    // 遍历主机并添加告警信息
                    foreach ($rackHosts as &$host) {
                        $usedU += ($host['u_end'] - $host['u_start'] + 1);
                        $hostId = $host['hostid'];
                        
                        if (isset($hostProblemsData[$hostId])) {
                            $host['problem_count'] = $hostProblemsData[$hostId]['count'];
                            $host['problems'] = $hostProblemsData[$hostId]['problems'];
                            // 获取最高严重程度
                            $host['max_severity'] = 0;
                            foreach ($hostProblemsData[$hostId]['problems'] as $problem) {
                                if ($problem['severity'] > $host['max_severity']) {
                                    $host['max_severity'] = $problem['severity'];
                                }
                                // 更新机柜级别的最高严重程度
                                if ($problem['severity'] > $maxSeverity) {
                                    $maxSeverity = $problem['severity'];
                                }
                            }
                            $rackProblems += $hostProblemsData[$hostId]['count'];
                        } else {
                            $host['problem_count'] = 0;
                            $host['problems'] = [];
                            $host['max_severity'] = -1; // 无告警
                        }
                        
                        // 将groups数组转换为字符串，方便前端显示
                        if (is_array($host['groups'])) {
                            $host['groups'] = implode(', ', $host['groups']);
                        }
                        // 添加ip别名，确保前端可以访问
                        $host['ip'] = $host['main_ip'] ?? '';
                    }
                    unset($host); // 解除引用
                }
                
                $allRacksData[] = [
                    'id' => $rack['id'],
                    'name' => $rack['name'],
                    'room_id' => $rack['room_id'] ?? $roomId,
                    'height' => $rack['height'] ?? 42,
                    'description' => $rack['description'] ?? '',
                    'host_count' => $hostCount,
                    'used_u' => $usedU,
                    'problem_count' => $rackProblems,
                    'max_severity' => $maxSeverity,
                    'hosts' => $rackHosts
                ];
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
            'lang' => LanguageManager::class,
            'show_overview' => $showOverview,
            'all_racks_data' => $allRacksData
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_view'));
        
        $this->setResponse($response);
    }
}
