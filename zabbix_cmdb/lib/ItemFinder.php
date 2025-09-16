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
}
