<?php

namespace Modules\ZabbixCmdb\Lib;

use API;

require_once __DIR__ . '/ItemConfig.php';

class ItemFinder {
    
    /**
     * 查找CPU数量监控项
     */
    public static function findCpuCount($hostid) {
        $items = self::batchGetHostItems([$hostid], ['cpu_count']);
        return $items[$hostid]['cpu_count'];
    }
    
    /**
     * 查找内存总量监控项
     */
    public static function findMemoryTotal($hostid) {
        $items = self::batchGetHostItems([$hostid], ['memory_total']);
        return $items[$hostid]['memory_total'];
    }

    /**
     * 查找CPU使用率监控项
     */
    public static function findCpuUsage($hostid) {
        $items = self::batchGetHostItems([$hostid], ['cpu_usage']);
        return $items[$hostid]['cpu_usage'];
    }

    /**
     * 查找内存使用率监控项
     */
    public static function findMemoryUsage($hostid) {
        $items = self::batchGetHostItems([$hostid], ['memory_usage']);
        return $items[$hostid]['memory_usage'];
    }

        /**
     * 查找内核版本监控项
     */
    public static function findKernelVersion($hostid) {
        $items = self::batchGetHostItems([$hostid], ['kernel_version']);
        return $items[$hostid]['kernel_version'];
    }
    
    /**
     * 查找系统名称监控项
     */
    public static function findSystemName($hostid) {
        $items = self::batchGetHostItems([$hostid], ['system_name']);
        return $items[$hostid]['system_name'];
    }
    
    /**
     * 查找操作系统监控项
     */
    public static function findOperatingSystem($hostid) {
        $items = self::batchGetHostItems([$hostid], ['operating_system']);
        return $items[$hostid]['operating_system'];
    }
    
    /**
     * 查找操作系统架构监控项
     */
    public static function findOsArchitecture($hostid) {
        $items = self::batchGetHostItems([$hostid], ['os_architecture']);
        return $items[$hostid]['os_architecture'];
    }
    
    /** Fetch candidates in batches, then apply JSON rules in their declared order. */
    public static function batchGetHostItems(array $hostIds, array $categories = []): array {
        if (!$hostIds) {
            return [];
        }
        $rules = ItemConfig::load();
        if ($categories) {
            $rules = array_intersect_key($rules, array_flip($categories));
        }
        $result = array_fill_keys($hostIds, array_fill_keys(array_keys($rules), null));
        $search = [];
        foreach ($rules as $categoryRules) {
            foreach ($categoryRules as $rule) {
                // API search narrows candidates; local matching enforces exact/fuzzy semantics.
                $search[$rule['field']][] = $rule['match'] === 'fuzzy'
                    ? '*'.$rule['pattern'].'*' : $rule['pattern'];
            }
        }
        if (!$search) {
            return $result;
        }
        foreach ($search as &$patterns) {
            $patterns = array_values(array_unique($patterns));
        }
        unset($patterns);
        foreach (array_chunk($hostIds, 200) as $chunk) {
            $items = API::Item()->get([
                'output' => ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'value_type'],
                'hostids' => $chunk,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => $search,
                'searchByAny' => true,
                'searchWildcardsEnabled' => true
            ]);
            // Stable tie-break for multiple items matching the same rule.
            usort($items, function ($a, $b) {
                return strnatcmp($a['itemid'], $b['itemid']);
            });
            $byHost = [];
            foreach ($items as $item) {
                $byHost[$item['hostid']][] = $item;
            }
            foreach ($chunk as $hostid) {
                foreach ($rules as $category => $categoryRules) {
                    $selected = self::selectItem($byHost[$hostid] ?? [], $categoryRules);
                    if ($selected === null) {
                        continue;
                    }
                    $item = $selected['item'];
                    $value = $item['lastvalue'] ?? '';
                    if ($value === '') {
                        $history = API::History()->get([
                            'output' => ['value'], 'itemids' => [$item['itemid']],
                            'history' => (int) $item['value_type'],
                            'sortfield' => ['clock', 'ns'], 'sortorder' => 'DESC', 'limit' => 1
                        ]);
                        $value = $history[0]['value'] ?? '';
                    }
                    if ($value !== '' && $selected['rule']['transform'] === 'subtract_from_100'
                            && is_numeric($value)) {
                        $value = 100 - (float) $value;
                    }
                    $result[$hostid][$category] = [
                        'item' => $item, 'value' => $value,
                        'key' => $item['key_'], 'value_type' => $item['value_type']
                    ];
                }
            }
        }
        return $result;
    }

    public static function selectItem(array $items, array $rules) {
        foreach ($rules as $rule) {
            foreach ($items as $item) {
                $value = $item[$rule['field']] ?? '';
                $matched = $rule['match'] === 'exact'
                    ? $value === $rule['pattern']
                    : preg_match('/'.str_replace('\\*', '.*', preg_quote($rule['pattern'], '/')).'/iu', $value) === 1;
                if ($matched) {
                    return ['item' => $item, 'rule' => $rule];
                }
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
