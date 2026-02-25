<?php

namespace Modules\ZabbixCmdb\Lib;

use API;

class ItemFinder {
    
    /**
     * 查找CPU数量监控项
     */
    public static function findCpuCount($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.cpu.num']],
            ['filter' => ['key_' => 'system.hw.cpu.num']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Number of CPUs'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Number of cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.num'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * 查找内存总量监控项
     */
    public static function findMemoryTotal($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'vm.memory.size[total]']],
            ['filter' => ['key_' => 'vm.memory.total']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Total memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory total'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.size'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

    /**
     * 查找CPU使用率监控项
     */
    public static function findCpuUsage($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.cpu.util[,avg1]']],
            ['filter' => ['key_' => 'system.cpu.util[]']],
            ['filter' => ['key_' => 'system.cpu.util']],
            ['filter' => ['key_' => 'system.cpu.load[avg1]']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'CPU utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU Utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Processor load'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.util'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.load'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

    /**
     * 查找内存使用率监控项
     */
    public static function findMemoryUsage($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'vm.memory.util[]']],
            ['filter' => ['key_' => 'vm.memory.util']],
            ['filter' => ['key_' => 'vm.memory.pused']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Memory utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory Utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Used memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'memory.util'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'memory.pused'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

        /**
     * 查找内核版本监控项
     */
    public static function findKernelVersion($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.uname']],
            ['filter' => ['key_' => 'system.sw.os[uname]']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'System uname'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Kernel version'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.uname'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * 查找系统名称监控项
     */
    public static function findSystemName($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.hostname']],
            ['filter' => ['key_' => 'system.sw.os[hostname]']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'System name'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Hostname'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.hostname'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * 查找操作系统监控项
     */
    public static function findOperatingSystem($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.sw.os']],
            ['filter' => ['key_' => 'system.sw.os[name]']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Operating system'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'OS name'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.sw.os'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * 查找操作系统架构监控项
     */
    public static function findOsArchitecture($hostid) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.sw.arch']],
            ['filter' => ['key_' => 'system.hw.arch']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Operating system architecture'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'System architecture'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.sw.arch'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * 批量获取多台主机的监控项数据（高性能三阶段版本）
     *
     * 阶段1：使用 filter.key_ 精确匹配标准 key（单次 API 调用，覆盖绝大部分场景）
     * 阶段2：对阶段1未命中的主机/类别，使用 search 模糊匹配兜底
     * 阶段3：对 lastvalue 为空的 item，批量查询 History API 获取最近的值
     *
     * 性能对比：
     * - 原方案：N 台主机 × 8 类别 × 3-6 模式/类别 = 24N~48N 次 API 调用
     * - 新方案：1 次精确匹配 + 0~8 次模糊兜底 + 1~3 次 History = 最多 12 次 API 调用
     *
     * @param array $hostIds 主机ID数组
     * @return array 按主机ID索引的监控项数据 [hostid => [category => [value, key, value_type]|null]]
     */
    public static function batchGetHostItems(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }

        // 初始化结果结构
        $result = [];
        foreach ($hostIds as $hostid) {
            $result[$hostid] = [
                'cpu_count'        => null,
                'cpu_usage'        => null,
                'memory_total'     => null,
                'memory_usage'     => null,
                'kernel_version'   => null,
                'system_name'      => null,
                'operating_system' => null,
                'os_architecture'  => null,
            ];
        }

        // ═══════════════════════════════════════════
        //  阶段1：精确 Key 匹配（单次 API 调用）
        // ═══════════════════════════════════════════

        // 定义所有已知的监控项 key，按类别和优先级排列（索引越小优先级越高）
        $keyMap = [
            'cpu_count' => [
                'system.cpu.num',
                'system.cpu.num[online]',
                'system.hw.cpu.num',
            ],
            'cpu_usage' => [
                'system.cpu.util',
                'system.cpu.util[,avg1]',
                'system.cpu.util[,idle]',
                'system.cpu.util[]',
                'system.cpu.load[avg1]',
                'system.cpu.load[all,avg1]',
                'system.cpu.load[percpu,avg1]',
            ],
            'memory_total' => [
                'vm.memory.size[total]',
                'vm.memory.total',
            ],
            'memory_usage' => [
                'vm.memory.utilization',
                'vm.memory.util',
                'vm.memory.util[]',
                'vm.memory.size[pused]',
                'vm.memory.size[pavailable]',
                'vm.memory.pused',
            ],
            'kernel_version' => [
                'system.uname',
                'system.sw.os[uname]',
            ],
            'system_name' => [
                'system.hostname',
                'system.hostname[host]',
                'system.sw.os[hostname]',
                'agent.hostname',
            ],
            'operating_system' => [
                'system.sw.os',
                'system.sw.os[full]',
                'system.sw.os[name]',
                'system.sw.os[short]',
            ],
            'os_architecture' => [
                'system.sw.arch',
                'system.hw.arch',
            ],
        ];

        // 收集所有 key 并构建反向索引：key → [category, priority]
        $allKeys = [];
        $keyIndex = [];
        foreach ($keyMap as $category => $keys) {
            foreach ($keys as $priority => $key) {
                $allKeys[] = $key;
                $keyIndex[$key] = ['category' => $category, 'priority' => $priority];
            }
        }

        // 跨阶段共享：追踪需要 History API 回退的 item
        $needsHistory = [];

        // 单次 API 调用获取所有精确匹配的监控项
        try {
            $items = API::Item()->get([
                'output'       => ['itemid', 'hostid', 'key_', 'lastvalue', 'value_type'],
                'hostids'      => $hostIds,
                'filter'       => [
                    'key_'   => $allKeys,
                    'status' => ITEM_STATUS_ACTIVE,
                ],
                'preservekeys' => false,
            ]);

            // 按 hostid + category 追踪当前已选的最高优先级
            $currentPriority = [];

            foreach ($items as $item) {
                $hostid = $item['hostid'];
                $key    = $item['key_'];

                if (!isset($result[$hostid]) || !isset($keyIndex[$key])) {
                    continue;
                }

                $category = $keyIndex[$key]['category'];
                $priority = $keyIndex[$key]['priority'];
                $prioKey  = $hostid . ':' . $category;
                $hasValue = isset($item['lastvalue']) && $item['lastvalue'] !== '';

                if ($hasValue) {
                    // 有值的 item — 最高优先级
                    if (!isset($currentPriority[$prioKey]) || $priority < $currentPriority[$prioKey]) {
                        $result[$hostid][$category] = [
                            'value'      => $item['lastvalue'],
                            'key'        => $key,
                            'value_type' => $item['value_type'],
                        ];
                        $currentPriority[$prioKey] = $priority;
                        unset($needsHistory[$prioKey]);
                    }
                } else {
                    // lastvalue 为空 — 先记录（低优先级），等 History API 回退
                    if (!isset($currentPriority[$prioKey])) {
                        $result[$hostid][$category] = [
                            'value'      => '',
                            'key'        => $key,
                            'value_type' => $item['value_type'],
                        ];
                        $currentPriority[$prioKey] = $priority + 10000;
                        $needsHistory[$prioKey] = [
                            'hostid'     => $hostid,
                            'category'   => $category,
                            'itemid'     => $item['itemid'],
                            'value_type' => $item['value_type'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('CMDB batchGetHostItems phase1: ' . $e->getMessage());
        }

        // ═══════════════════════════════════════════
        //  阶段2：搜索兜底（仅针对阶段1未命中的主机/类别）
        //  搜索模式与原始 findXxx() 方法保持一致
        // ═══════════════════════════════════════════

        $searchPatterns = [
            'cpu_count' => [
                ['search' => ['key_' => 'cpu.num'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Number of CPUs'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Number of cores'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'CPU cores'], 'searchWildcardsEnabled' => true],
            ],
            'cpu_usage' => [
                ['search' => ['key_' => 'cpu.util'], 'searchWildcardsEnabled' => true],
                ['search' => ['key_' => 'cpu.load'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'CPU utilization'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'CPU usage'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Processor load'], 'searchWildcardsEnabled' => true],
            ],
            'memory_total' => [
                ['search' => ['key_' => 'vm.memory.size'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Total memory'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Memory total'], 'searchWildcardsEnabled' => true],
            ],
            'memory_usage' => [
                ['search' => ['key_' => 'memory.util'], 'searchWildcardsEnabled' => true],
                ['search' => ['key_' => 'memory.pused'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Memory utilization'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Memory usage'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Used memory'], 'searchWildcardsEnabled' => true],
            ],
            'kernel_version' => [
                ['search' => ['key_' => 'system.uname'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'System uname'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Kernel version'], 'searchWildcardsEnabled' => true],
            ],
            'system_name' => [
                ['search' => ['key_' => 'hostname'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'System name'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Hostname'], 'searchWildcardsEnabled' => true],
            ],
            'operating_system' => [
                ['search' => ['key_' => 'system.sw.os'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Operating system'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'OS name'], 'searchWildcardsEnabled' => true],
            ],
            'os_architecture' => [
                ['search' => ['key_' => '.arch'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'Operating system architecture'], 'searchWildcardsEnabled' => true],
                ['search' => ['name' => 'System architecture'], 'searchWildcardsEnabled' => true],
            ],
        ];

        foreach ($searchPatterns as $category => $patterns) {
            // 找出该类别缺失数据的主机
            $missingHostIds = [];
            foreach ($hostIds as $hostid) {
                if ($result[$hostid][$category] === null) {
                    $missingHostIds[] = $hostid;
                }
            }
            if (empty($missingHostIds)) {
                continue;
            }

            // 按模式依次搜索（每个模式只搜索仍缺失的主机）
            foreach ($patterns as $pattern) {
                if (empty($missingHostIds)) {
                    break;
                }

                try {
                    $searchParams = array_merge([
                        'output'  => ['itemid', 'hostid', 'key_', 'lastvalue', 'value_type'],
                        'hostids' => $missingHostIds,
                        'filter'  => ['status' => ITEM_STATUS_ACTIVE],
                    ], $pattern);

                    $foundItems = API::Item()->get($searchParams);

                    foreach ($foundItems as $item) {
                        $hostid = $item['hostid'];
                        if (isset($result[$hostid]) && $result[$hostid][$category] === null) {
                            $hasVal = isset($item['lastvalue']) && $item['lastvalue'] !== '';
                            $result[$hostid][$category] = [
                                'value'      => $item['lastvalue'] ?? '',
                                'key'        => $item['key_'],
                                'value_type' => $item['value_type'],
                            ];
                            if (!$hasVal) {
                                $prioKey2 = $hostid . ':' . $category;
                                $needsHistory[$prioKey2] = [
                                    'hostid'     => $hostid,
                                    'category'   => $category,
                                    'itemid'     => $item['itemid'],
                                    'value_type' => $item['value_type'],
                                ];
                            }
                        }
                    }

                    // 更新缺失主机列表
                    $newMissing = [];
                    foreach ($missingHostIds as $hid) {
                        if ($result[$hid][$category] === null) {
                            $newMissing[] = $hid;
                        }
                    }
                    $missingHostIds = $newMissing;
                } catch (\Exception $e) {
                    error_log('CMDB batchSearchFallback ' . $category . ': ' . $e->getMessage());
                }
            }
        }

        // ═══════════════════════════════════════════
        //  阶段3：History API 回退
        //  对 lastvalue 为空的 item，查询历史表获取最近的值
        //  与原始 findItemByPatterns() 的 History 回退逻辑一致
        // ═══════════════════════════════════════════

        if (!empty($needsHistory)) {
            // 按 history 表类型分组
            $byHistType = [];
            foreach ($needsHistory as $prioKey => $info) {
                $histType = self::getHistoryType($info['value_type']);
                $byHistType[$histType][$prioKey] = $info;
            }

            foreach ($byHistType as $histType => $items) {
                $itemIds = array_column($items, 'itemid');

                try {
                    $historyRecords = API::History()->get([
                        'output'    => ['itemid', 'value'],
                        'itemids'   => $itemIds,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit'     => count($itemIds) * 3,
                        'history'   => $histType,
                    ]);

                    // 按 itemid 去重，只保留每个 item 最近一条
                    $latestByItem = [];
                    foreach ($historyRecords as $rec) {
                        if (!isset($latestByItem[$rec['itemid']])) {
                            $latestByItem[$rec['itemid']] = $rec['value'];
                        }
                    }

                    // 更新结果
                    foreach ($items as $prioKey => $info) {
                        if (isset($latestByItem[$info['itemid']]) && $latestByItem[$info['itemid']] !== '') {
                            $result[$info['hostid']][$info['category']]['value'] = $latestByItem[$info['itemid']];
                        }
                    }
                } catch (\Exception $e) {
                    error_log('CMDB batchGetHostItems phase3: ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * 根据 item 的 value_type 确定 History API 的 history 参数
     */
    private static function getHistoryType($valueType): int {
        switch ((string)$valueType) {
            case (string)ITEM_VALUE_TYPE_FLOAT:   // 0
                return 0;
            case (string)ITEM_VALUE_TYPE_UINT64:  // 3
                return 3;
            case (string)ITEM_VALUE_TYPE_STR:     // 1
            case (string)ITEM_VALUE_TYPE_TEXT:    // 4
            case (string)ITEM_VALUE_TYPE_LOG:     // 2
            default:
                return 1;
        }
    }

    /**
     * 根据模式列表查找监控项并获取值
     */
    private static function findItemByPatterns($hostid, $patterns) {
        foreach ($patterns as $pattern) {
            $searchParams = array_merge([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'value_type'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'limit' => 1
            ], $pattern);
            
            $items = API::Item()->get($searchParams);
            
            if (!empty($items)) {
                $item = $items[0];
                $value = null;
                
                // 首先尝试使用最后一个值
                if (isset($item['lastvalue']) && $item['lastvalue'] !== '') {
                    $value = $item['lastvalue'];
                }
                
                // 如果没有最后一个值，尝试获取最新的历史数据
                if ($value === null || $value === '') {
                    $historyType = 0; // 默认为数值类型
                    
                    // 根据value_type确定history表类型
                    switch ($item['value_type']) {
                        case ITEM_VALUE_TYPE_FLOAT:
                            $historyType = 0;
                            break;
                        case ITEM_VALUE_TYPE_UINT64:
                            $historyType = 3;
                            break;
                        case ITEM_VALUE_TYPE_STR:
                        case ITEM_VALUE_TYPE_TEXT:
                        case ITEM_VALUE_TYPE_LOG:
                            $historyType = 1;
                            break;
                    }
                    
                    $recentHistory = API::History()->get([
                        'output' => ['value'],
                        'itemids' => $item['itemid'],
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1,
                        'history' => $historyType
                    ]);
                    
                    if (!empty($recentHistory)) {
                        $value = $recentHistory[0]['value'];
                    }
                }
                
                return [
                    'item' => $item,
                    'value' => $value
                ];
            }
        }
        
        return null;
    }

    /**
     * 格式化内存大小
     */
    public static function formatMemorySize($bytes) {
        if (empty($bytes) || !is_numeric($bytes)) {
            return '-';
        }
        
        $bytes = floatval($bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * 提取内核版本信息
     */
    public static function extractKernelInfo($fullString) {
        if (empty($fullString)) {
            return '-';
        }

        // 如果字符串太长，尝试提取关键信息
        if (strlen($fullString) > 50) {
            // 尝试提取Linux内核版本
            if (preg_match('/Linux\s+\S+\s+(\S+)/', $fullString, $matches)) {
                return $matches[1];
            }
            
            // 尝试提取Windows版本信息
            if (preg_match('/Windows\s+[^0-9]*([0-9]+[^,\s]*)/i', $fullString, $matches)) {
                return 'Windows ' . $matches[1];
            }
            
            // 如果是其他系统，截取前50个字符
            return substr($fullString, 0, 47) . '...';
        }
        
        return $fullString;
    }

    /**
     * 获取主机接口可用性状态（基于Zabbix原生接口可用性）
     * 返回状态信息数组
     */
    public static function getHostAvailabilityStatus($hostid, $interfaces = []) {
        try {
            // 如果没有传入接口信息，从API获取
            if (empty($interfaces)) {
                $interfaces = API::HostInterface()->get([
                    'hostids' => [$hostid],
                    'output' => ['interfaceid', 'type', 'main', 'available', 'error']
                ]);
            }

            if (empty($interfaces)) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // 查找主接口的可用性状态
            $mainInterface = null;
            foreach ($interfaces as $interface) {
                if ($interface['main'] == 1) {
                    $mainInterface = $interface;
                    break;
                }
            }

            // 如果没有主接口，使用第一个接口
            if (!$mainInterface && !empty($interfaces)) {
                $mainInterface = $interfaces[0];
            }

            if (!$mainInterface) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // 根据Zabbix接口可用性状态返回对应的显示
            // available: 0=未知, 1=可用, 2=不可用
            switch ($mainInterface['available']) {
                case '1':
                    return ['status' => 'available', 'text' => 'Available', 'class' => 'status-available'];
                case '2':
                    return ['status' => 'unavailable', 'text' => 'Unavailable', 'class' => 'status-unavailable'];
                case '0':
                default:
                    return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

        } catch (Exception $e) {
            error_log("Failed to check host availability for {$hostid}: " . $e->getMessage());
            return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
        }
    }
}
