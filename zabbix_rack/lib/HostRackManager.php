<?php
/**
 * 主机机柜管理器
 * 负责主机与机柜位置的关联管理（通过 Zabbix 主机标签）
 */

namespace Modules\ZabbixRack\Lib;

require_once __DIR__ . '/ZabbixVersion.php';

class HostRackManager {
    
    // 标签名称常量
    const TAG_ROOM = 'rack_room';
    const TAG_RACK = 'rack_name';
    const TAG_U_START = 'rack_u_start';
    const TAG_U_END = 'rack_u_end';
    
    /**
     * 获取主机组查询参数名（兼容 Zabbix 6/7）
     * Zabbix 7.0+: selectHostGroups
     * Zabbix 6.x:  selectGroups
     */
    private static function getSelectGroupsParam(): string {
        return ZabbixVersion::isVersion7() ? 'selectHostGroups' : 'selectGroups';
    }
    
    /**
     * 获取标签操作符常量值（兼容不同Zabbix版本）
     */
    private static function getTagsOperatorEqual(): int {
        if (defined('TAGS_OPERATOR_EQUAL')) {
            return TAGS_OPERATOR_EQUAL;
        }
        return 0; // 默认值
    }
    
    /**
     * 获取标签评估类型常量值（兼容不同Zabbix版本）
     */
    private static function getTagEvalTypeAndOr(): int {
        if (defined('TAG_EVAL_TYPE_AND_OR')) {
            return TAG_EVAL_TYPE_AND_OR;
        }
        return 0; // 默认值 AND/OR
    }
    
    /**
     * 获取机柜中的所有主机
     * 
     * @param string $roomName 机房名称
     * @param string $rackName 机柜名称
     * @return array 主机列表，包含位置信息
     */
    public static function getHostsInRack(string $roomName, string $rackName): array {
        $hosts = \API::Host()->get([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectTags' => 'extend',
            self::getSelectGroupsParam() => ['groupid', 'name'],
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'tags' => [
                ['tag' => self::TAG_ROOM, 'value' => $roomName, 'operator' => self::getTagsOperatorEqual()],
                ['tag' => self::TAG_RACK, 'value' => $rackName, 'operator' => self::getTagsOperatorEqual()]
            ],
            'evaltype' => self::getTagEvalTypeAndOr()
        ]);
        
        $result = [];
        foreach ($hosts as $host) {
            $uStart = null;
            $uEnd = null;
            
            foreach ($host['tags'] as $tag) {
                if ($tag['tag'] === self::TAG_U_START) {
                    $uStart = (int)$tag['value'];
                } elseif ($tag['tag'] === self::TAG_U_END) {
                    $uEnd = (int)$tag['value'];
                }
            }
            
            // 获取主接口IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            // 获取主机组名称 - 兼容 Zabbix 6 (groups) 和 Zabbix 7 (hostgroups)
            $groups = [];
            $hostGroups = isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []);
            foreach ($hostGroups as $group) {
                $groups[] = $group['name'];
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'u_start' => $uStart,
                'u_end' => $uEnd,
                'u_height' => ($uStart && $uEnd) ? ($uEnd - $uStart + 1) : 1,
                'main_ip' => $mainIp,
                'groups' => $groups
            ];
        }
        
        // 按U位排序
        usort($result, function($a, $b) {
            return ($b['u_start'] ?? 0) - ($a['u_start'] ?? 0);
        });
        
        return $result;
    }
    
    /**
     * 分配主机到机柜位置
     * 
     * @param string $hostId 主机ID
     * @param string $roomName 机房名称
     * @param string $rackName 机柜名称
     * @param int $uStart 起始U位
     * @param int $uEnd 结束U位
     * @return bool 是否成功
     */
    public static function assignHost(string $hostId, string $roomName, string $rackName, int $uStart, int $uEnd): bool {
        // 获取当前主机标签
        $hosts = \API::Host()->get([
            'output' => ['hostid'],
            'selectTags' => 'extend',
            'hostids' => [$hostId]
        ]);
        
        if (empty($hosts)) {
            return false;
        }
        
        $host = $hosts[0];
        $tags = $host['tags'] ?? [];
        
        // 移除旧的机柜标签
        $tags = array_filter($tags, function($tag) {
            return !in_array($tag['tag'], [
                self::TAG_ROOM,
                self::TAG_RACK,
                self::TAG_U_START,
                self::TAG_U_END
            ]);
        });
        $tags = array_values($tags);
        
        // 添加新的机柜标签
        $tags[] = ['tag' => self::TAG_ROOM, 'value' => $roomName];
        $tags[] = ['tag' => self::TAG_RACK, 'value' => $rackName];
        $tags[] = ['tag' => self::TAG_U_START, 'value' => (string)$uStart];
        $tags[] = ['tag' => self::TAG_U_END, 'value' => (string)$uEnd];
        
        // 更新主机标签
        $result = \API::Host()->update([
            'hostid' => $hostId,
            'tags' => $tags
        ]);
        
        return !empty($result);
    }
    
    /**
     * 从机柜移除主机
     * 
     * @param string $hostId 主机ID
     * @return bool 是否成功
     */
    public static function removeHost(string $hostId): bool {
        // 获取当前主机标签
        $hosts = \API::Host()->get([
            'output' => ['hostid'],
            'selectTags' => 'extend',
            'hostids' => [$hostId]
        ]);
        
        if (empty($hosts)) {
            return false;
        }
        
        $host = $hosts[0];
        $tags = $host['tags'] ?? [];
        
        // 移除机柜相关标签
        $tags = array_filter($tags, function($tag) {
            return !in_array($tag['tag'], [
                self::TAG_ROOM,
                self::TAG_RACK,
                self::TAG_U_START,
                self::TAG_U_END
            ]);
        });
        $tags = array_values($tags);
        
        // 更新主机标签
        $result = \API::Host()->update([
            'hostid' => $hostId,
            'tags' => $tags
        ]);
        
        return !empty($result);
    }
    
    /**
     * 获取可分配的主机列表
     * 
     * @param string|null $groupId 主机组ID（可选）
     * @param string|null $search 搜索关键字（可选）
     * @return array 主机列表
     */
    public static function getAvailableHosts(?string $groupId = null, ?string $search = null): array {
        $options = [
            'output' => ['hostid', 'host', 'name', 'status'],
            self::getSelectGroupsParam() => ['groupid', 'name'],
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'selectTags' => 'extend',
            'sortfield' => 'name',
            'sortorder' => 'ASC',
            'limit' => 100
        ];
        
        if ($groupId) {
            $options['groupids'] = [$groupId];
        }
        
        if ($search) {
            $options['search'] = [
                'name' => $search,
                'host' => $search
            ];
            $options['searchByAny'] = true;
        }
        
        $hosts = \API::Host()->get($options);
        
        $result = [];
        foreach ($hosts as $host) {
            // 检查是否已分配到机柜
            $inRack = false;
            $currentRoom = '';
            $currentRack = '';
            
            foreach ($host['tags'] as $tag) {
                if ($tag['tag'] === self::TAG_RACK && !empty($tag['value'])) {
                    $inRack = true;
                    $currentRack = $tag['value'];
                }
                if ($tag['tag'] === self::TAG_ROOM && !empty($tag['value'])) {
                    $currentRoom = $tag['value'];
                }
            }
            
            // 获取主接口IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            // 获取主机组名称 - 兼容 Zabbix 6 (groups) 和 Zabbix 7 (hostgroups)
            $groups = [];
            $hostGroups = isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []);
            foreach ($hostGroups as $group) {
                $groups[] = $group['name'];
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'main_ip' => $mainIp,
                'groups' => $groups,
                'in_rack' => $inRack,
                'current_room' => $currentRoom,
                'current_rack' => $currentRack
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取所有主机组
     * 
     * @return array 主机组列表
     */
    public static function getHostGroups(): array {
        $groups = \API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]);
        
        return $groups;
    }
    
    /**
     * 检查U位是否可用
     * 
     * @param string $roomName 机房名称
     * @param string $rackName 机柜名称
     * @param int $uStart 起始U位
     * @param int $uEnd 结束U位
     * @param string|null $excludeHostId 排除的主机ID（用于编辑时）
     * @return bool 是否可用
     */
    public static function isPositionAvailable(string $roomName, string $rackName, int $uStart, int $uEnd, ?string $excludeHostId = null): bool {
        $hosts = self::getHostsInRack($roomName, $rackName);
        
        foreach ($hosts as $host) {
            if ($excludeHostId && $host['hostid'] === $excludeHostId) {
                continue;
            }
            
            $hostStart = $host['u_start'];
            $hostEnd = $host['u_end'];
            
            if ($hostStart === null || $hostEnd === null) {
                continue;
            }
            
            // 检查是否有重叠
            if (!($uEnd < $hostStart || $uStart > $hostEnd)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 搜索已分配到机柜的主机
     * 
     * @param string $keyword 搜索关键字
     * @return array 主机列表
     */
    public static function searchAssignedHosts(string $keyword): array {
        $options = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectTags' => 'extend',
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'search' => [
                'name' => $keyword,
                'host' => $keyword
            ],
            'searchByAny' => true,
            'limit' => 50
        ];
        
        $hosts = \API::Host()->get($options);
        
        $result = [];
        foreach ($hosts as $host) {
            $roomName = '';
            $rackName = '';
            $uStart = null;
            $uEnd = null;
            
            foreach ($host['tags'] as $tag) {
                switch ($tag['tag']) {
                    case self::TAG_ROOM:
                        $roomName = $tag['value'];
                        break;
                    case self::TAG_RACK:
                        $rackName = $tag['value'];
                        break;
                    case self::TAG_U_START:
                        $uStart = (int)$tag['value'];
                        break;
                    case self::TAG_U_END:
                        $uEnd = (int)$tag['value'];
                        break;
                }
            }
            
            // 只返回已分配到机柜的主机
            if (empty($rackName)) {
                continue;
            }
            
            // 获取主接口IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'main_ip' => $mainIp,
                'room_name' => $roomName,
                'rack_name' => $rackName,
                'u_start' => $uStart,
                'u_end' => $uEnd
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取主机告警数量
     * 
     * @param array $hostIds 主机ID数组
     * @return array 主机ID => 告警数量
     */
    public static function getHostProblems(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }
        
        $result = [];
        foreach ($hostIds as $hostId) {
            $result[$hostId] = [
                'count' => 0,
                'problems' => []
            ];
        }
        
        try {
            // 获取活跃告警 - Zabbix 7.4 的 problem.get 只支持 eventid 排序
            $problems = \API::Problem()->get([
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged'],
                'hostids' => $hostIds,
                'recent' => true,
                'sortfield' => 'eventid',
                'sortorder' => 'DESC',
                'limit' => 1000
            ]);
            
            // 检查 API 返回值
            if (!is_array($problems) || empty($problems)) {
                return $result;
            }
            
            // 在 PHP 中按严重程度和时间排序
            usort($problems, function($a, $b) {
                if ($a['severity'] != $b['severity']) {
                    return $b['severity'] - $a['severity']; // 严重程度降序
                }
                return $b['clock'] - $a['clock']; // 时间降序
            });
            
            // 获取每个告警对应的主机ID
            $triggerIds = array_unique(array_column($problems, 'objectid'));
            $triggerHostMap = [];
            
            if (!empty($triggerIds)) {
                $triggers = \API::Trigger()->get([
                    'output' => ['triggerid'],
                    'selectHosts' => ['hostid'],
                    'triggerids' => $triggerIds,
                    'preservekeys' => true
                ]);
                
                // 检查 triggers 返回值
                if (!is_array($triggers)) {
                    $triggers = [];
                }
                
                foreach ($triggers as $triggerId => $trigger) {
                    if (!empty($trigger['hosts'])) {
                        $triggerHostMap[$triggerId] = $trigger['hosts'][0]['hostid'];
                    }
                }
            }
            
            // 统计每个主机的告警
            foreach ($problems as $problem) {
                $triggerId = $problem['objectid'];
                if (isset($triggerHostMap[$triggerId])) {
                    $hostId = $triggerHostMap[$triggerId];
                    if (isset($result[$hostId])) {
                        $result[$hostId]['count']++;
                        $result[$hostId]['problems'][] = [
                            'eventid' => $problem['eventid'],
                            'name' => $problem['name'],
                            'severity' => $problem['severity'],
                            'clock' => $problem['clock'],
                            'acknowledged' => $problem['acknowledged']
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("HostRackManager::getHostProblems error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * 获取单个主机的告警详情
     * 
     * @param string $hostId 主机ID
     * @return array 告警列表
     */
    public static function getHostProblemDetails(string $hostId): array {
        try {
            $problems = \API::Problem()->get([
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged', 'r_clock'],
                'hostids' => [$hostId],
                'recent' => true,
                'sortfield' => 'eventid',
                'sortorder' => 'DESC',
                'limit' => 100
            ]);
            
            // 检查 API 返回值
            if (!is_array($problems)) {
                return [];
            }
            
            // 在 PHP 中按严重程度和时间排序
            usort($problems, function($a, $b) {
                if ($a['severity'] != $b['severity']) {
                    return $b['severity'] - $a['severity'];
                }
                return $b['clock'] - $a['clock'];
            });
            
            $result = [];
            foreach ($problems as $problem) {
                $severityNames = [
                    LanguageManager::t('severity_not_classified'),
                    LanguageManager::t('severity_information'),
                    LanguageManager::t('severity_warning'),
                    LanguageManager::t('severity_average'),
                    LanguageManager::t('severity_high'),
                    LanguageManager::t('severity_disaster'),
                ];
                $result[] = [
                    'eventid' => $problem['eventid'],
                    'name' => $problem['name'],
                    'severity' => $problem['severity'],
                    'severity_name' => $severityNames[$problem['severity']] ?? LanguageManager::t('severity_unknown'),
                    'clock' => $problem['clock'],
                    'time' => date('Y-m-d H:i:s', $problem['clock']),
                    'acknowledged' => $problem['acknowledged']
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("HostRackManager::getHostProblemDetails error: " . $e->getMessage());
            return [];
        }
    }
}
