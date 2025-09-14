<?php

namespace Modules\ZabbixReports\Lib;

use API;

class ItemFinder {
    
    /**
     * 查找CPU使用率监控项
     */
    public static function findCpuUtilization($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.cpu.util']],
            ['filter' => ['key_' => 'system.cpu.util[]']],
            ['filter' => ['key_' => 'system.cpu.util[,user]']],
            ['filter' => ['key_' => 'system.cpu.util[,system]']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'CPU utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.cpu.util'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * 查找CPU数量监控项
     */
    public static function findCpuCount($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'system.cpu.num']],
            ['filter' => ['key_' => 'system.hw.cpu.num']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Number of CPUs'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.num'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * 查找内存使用率监控项
     */
    public static function findMemoryUtilization($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'vm.memory.utilization']],
            ['filter' => ['key_' => 'vm.memory.util[pused]']],
            ['filter' => ['key_' => 'vm.memory.pused']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Memory utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.util'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * 查找内存总量监控项
     */
    public static function findMemoryTotal($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // 优先使用精确的key
            ['filter' => ['key_' => 'vm.memory.size[total]']],
            ['filter' => ['key_' => 'vm.memory.total']],
            // 按名称搜索作为备选
            ['search' => ['name' => 'Total memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory total'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.size'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }

    /**
     * 查找内存大小监控项（别名方法，与findMemoryTotal相同）
     */
    public static function findMemorySize($hostid, $timeFrom = null, $timeTill = null) {
        return self::findMemoryTotal($hostid, $timeFrom, $timeTill);
    }
    
    /**
     * 根据模式列表查找监控项并获取值
     */
    private static function findItemByPatterns($hostid, $patterns, $timeFrom = null, $timeTill = null) {
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
                
                // 首先尝试获取历史数据
                if ($timeFrom !== null && $timeTill !== null) {
                    $history = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $item['itemid'],
                        'time_from' => $timeFrom,
                        'time_till' => $timeTill,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 10  // 获取多个值以确保有数据
                    ]);
                    
                    if (!empty($history)) {
                        // 计算平均值（对于CPU和内存使用率）
                        if (strpos($item['key_'], 'util') !== false || strpos($item['key_'], 'utilization') !== false) {
                            $sum = 0;
                            $count = count($history);
                            foreach ($history as $h) {
                                $sum += floatval($h['value']);
                            }
                            $value = $count > 0 ? $sum / $count : 0;
                        } else {
                            // 对于CPU数量和内存总量，使用最新值
                            $value = $history[0]['value'];
                        }
                    }
                }
                
                // 如果没有历史数据，使用最后一个值
                if ($value === null && isset($item['lastvalue']) && $item['lastvalue'] !== '') {
                    $value = $item['lastvalue'];
                }
                
                // 如果仍然没有值，尝试获取最新的历史数据
                if ($value === null) {
                    $recentHistory = API::History()->get([
                        'output' => ['value'],
                        'itemids' => $item['itemid'],
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
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
}
