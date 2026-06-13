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
require_once dirname(__DIR__) . '/lib/RackPermission.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\RackPermission;
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
            'search' => 'string',
            'side' => 'string',
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
        
        // 按当前用户权限过滤机房
        $rooms = RackPermission::filterRoomsForCurrentUser(RackConfig::getRooms());
        $allowedRoomNames = [];
        foreach ($rooms as $room) {
            $allowedRoomNames[(string) ($room['name'] ?? '')] = true;
        }

        if ($roomId !== '' && !RackPermission::userCanAccessRoomId($roomId, $rooms)) {
            $roomId = '';
            $rackId = '';
        }
        if ($rackId !== '' && !RackPermission::userCanAccessRackId($rackId)) {
            $rackId = '';
        }
        
        // 获取机柜列表
        $racks = [];
        if ($roomId) {
            $racks = RackPermission::filterRacksForCurrentUser(RackConfig::getRacks($roomId), $rooms);
        } elseif (!empty($rooms)) {
            // 默认选择第一个可访问机房
            $roomId = $rooms[0]['id'];
            $racks = RackPermission::filterRacksForCurrentUser(RackConfig::getRacks($roomId), $rooms);
        }
        
        // 获取当前选中的机柜
        $currentRack = null;
        $hosts = [];
        $hostsFront = [];
        $hostsBack = [];
        
        // 新增：所有机柜的数据（用于多机柜展示）
        $allRacksData = [];
        $showOverview = false; // 是否展示机柜概览模式
        
        if ($rackId) {
            // 用户选择了特定机柜，展示单机柜详情
            $currentRack = RackConfig::getRack($rackId);
            if ($currentRack !== null && !RackPermission::userCanAccessRackId($rackId)) {
                $currentRack = null;
                $rackId = '';
            }
            $showOverview = ($currentRack === null);
        } else {
            // 没有选择机柜，展示当前机房所有机柜概览
            $showOverview = true;
        }
        
        // 获取机柜中的主机 - 单机柜模式
        if ($currentRack) {
            $roomInfo = RackConfig::getRoom($currentRack['room_id']);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            $hostsFront = HostRackManager::getHostsInRack(
                $roomName,
                $currentRack['name'],
                HostRackManager::SIDE_FRONT
            );
            $hostsBack = HostRackManager::getHostsInRack(
                $roomName,
                $currentRack['name'],
                HostRackManager::SIDE_BACK
            );
            $hostsFront = HostRackManager::enrichHostsWithProblems($hostsFront);
            $hostsBack = HostRackManager::enrichHostsWithProblems($hostsBack);
            $hosts = array_merge($hostsFront, $hostsBack);
        }
        
        // 获取所有机柜的数据 - 概览模式
        if ($showOverview && !empty($racks)) {
            $roomInfo = RackConfig::getRoom($roomId);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            
            foreach ($racks as $rack) {
                $rackHostsFront = HostRackManager::getHostsInRack(
                    $roomName,
                    $rack['name'],
                    HostRackManager::SIDE_FRONT
                );
                $rackHostsBack = HostRackManager::getHostsInRack(
                    $roomName,
                    $rack['name'],
                    HostRackManager::SIDE_BACK
                );
                $rackProblems = 0;
                $maxSeverity = -1;
                $hostCount = count($rackHostsFront) + count($rackHostsBack);
                $usedUFront = 0;
                $usedUBack = 0;

                foreach ($rackHostsFront as $host) {
                    $usedUFront += ($host['u_end'] - $host['u_start'] + 1);
                }
                foreach ($rackHostsBack as $host) {
                    $usedUBack += ($host['u_end'] - $host['u_start'] + 1);
                }
                $usedU = max($usedUFront, $usedUBack);

                $rackHostsFront = HostRackManager::enrichHostsWithProblems($rackHostsFront);
                $rackHostsBack = HostRackManager::enrichHostsWithProblems($rackHostsBack);
                $rackHosts = array_merge($rackHostsFront, $rackHostsBack);

                foreach ($rackHosts as $host) {
                    if (($host['problem_count'] ?? 0) > 0) {
                        $rackProblems += (int) $host['problem_count'];
                        $hostSeverity = (int) ($host['max_severity'] ?? -1);
                        if ($hostSeverity > $maxSeverity) {
                            $maxSeverity = $hostSeverity;
                        }
                    }
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
                    'hosts' => $rackHosts,
                    'hosts_front' => $rackHostsFront,
                    'hosts_back' => $rackHostsBack,
                ];
            }
        }
        
        // 如果有搜索关键字，搜索主机
        $searchResults = [];
        if ($search) {
            $searchResults = HostRackManager::searchAssignedHosts($search);
            $searchResults = array_values(array_filter($searchResults, static function ($row) use ($allowedRoomNames) {
                $roomName = (string) ($row['room_name'] ?? '');
                return $roomName !== '' && isset($allowedRoomNames[$roomName]);
            }));

            // 在概览模式下，按搜索关键字过滤机柜显示
            if ($showOverview && !empty($allRacksData)) {
                // 收集搜索到的主机所在的机柜名
                $matchedRackNames = [];
                foreach ($searchResults as $sr) {
                    $matchedRackNames[$sr['rack_name']] = true;
                }

                // 同时支持按机柜名搜索
                $searchLower = mb_strtolower($search);
                $filteredRacks = [];
                foreach ($allRacksData as $rackData) {
                    $rackNameLower = mb_strtolower($rackData['name']);
                    // 机柜名匹配 或 包含搜索到的主机
                    if (mb_strpos($rackNameLower, $searchLower) !== false
                        || isset($matchedRackNames[$rackData['name']])) {
                        $filteredRacks[] = $rackData;
                    }
                }
                $allRacksData = $filteredRacks;
            }
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
            'hosts_front' => $hostsFront,
            'hosts_back' => $hostsBack,
            'search' => $search,
            'search_results' => $searchResults,
            'host_groups' => $hostGroups,
            'lang' => LanguageManager::class,
            'show_overview' => $showOverview,
            'all_racks_data' => $allRacksData,
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_view'));
        
        $this->setResponse($response);
    }
}
