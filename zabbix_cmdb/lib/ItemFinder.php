<?php

namespace Modules\ZabbixCmdb\Lib;

use API;

/**
 * 监控项查找器 - 优化版
 * 支持批量查询，减少API调用次数
 * 兼容 Zabbix 6.0、7.0、7.4 的监控项 key
 */
class ItemFinder {
    
    /**
     * 常见的监控项 key 模式
     * 覆盖 Zabbix 官方模板中的各种监控项
     */
    
    // CPU 数量的 key 模式
    private static $cpuCountKeys = [
        'system.cpu.num',           // Linux by Zabbix agent
        'system.hw.cpu.num',        // Hardware template
        'wmi.get[root\\cimv2,SELECT NumberOfLogicalProcessors FROM Win32_ComputerSystem]', // Windows
        'system.cpu.num[online]',   // Online CPUs
    ];
    
    // CPU 使用率的 key 模式
    private static $cpuUsageKeys = [
        'system.cpu.util',                      // Linux agent
        'system.cpu.util[,avg1]',               // Linux agent with parameter
        'system.cpu.util[,idle]',               // Idle CPU (需要计算 100 - value)
        'system.cpu.load[all,avg1]',            // CPU load
        'system.cpu.load[percpu,avg1]',         // Per CPU load
        'perf_counter_en["\\Processor Information(_Total)\\% Processor Utility"]', // Windows
        'perf_counter_en["\\Processor(_Total)\\% Processor Time"]', // Windows legacy
    ];
    
    // 内存总量的 key 模式
    private static $memoryTotalKeys = [
        'vm.memory.size[total]',    // Linux agent
        'vm.memory.size[available]', // Available memory
        'system.hw.memory',         // Hardware memory (SNMP)
        'wmi.get[root\\cimv2,SELECT TotalVisibleMemorySize FROM Win32_OperatingSystem]', // Windows
    ];
    
    // 内存使用率的 key 模式
    private static $memoryUsageKeys = [
        'vm.memory.util',                       // Linux (直接百分比)
        'vm.memory.size[pused]',                // Linux (使用百分比)
        'vm.memory.size[pavailable]',           // Available percentage (需要计算)
        'perf_counter_en["\\Memory\\% Committed Bytes In Use"]', // Windows
    ];
    
    // 系统名称/主机名的 key 模式
    private static $systemNameKeys = [
        'system.hostname',
        'system.hostname[host]',
        'system.hostname[shorthost]',
        'agent.hostname',
    ];
    
    // 操作系统的 key 模式
    private static $osKeys = [
        'system.sw.os',
        'system.sw.os[name]',
        'system.sw.os[full]',
        'system.uname',
        'vfs.file.contents[/etc/os-release]',
    ];
    
    // 系统架构的 key 模式
    private static $archKeys = [
        'system.sw.arch',
        'system.hw.arch',
        'vfs.file.contents[/proc/sys/kernel/arch]',
    ];
    
    // 内核版本的 key 模式
    private static $kernelKeys = [
        'system.uname',
        'system.sw.os[uname]',
        'vfs.file.contents[/proc/version]',
    ];
    
    /**
     * 批量获取多个主机的监控数据
     * 这是性能优化的核心方法，一次API调用获取所有主机的监控项
     * 
     * @param array $hostIds 主机ID数组
     * @return array 以hostid为键的监控数据数组
     */
    public static function batchFindItems(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }
        
        // 初始化结果数组
        $results = [];
        foreach ($hostIds as $hostId) {
            $results[$hostId] = [
                'cpu_total' => null,
                'cpu_usage' => null,
                'memory_total' => null,
                'memory_usage' => null,
                'system_name' => null,
                'operating_system' => null,
                'os_architecture' => null,
                'kernel_version' => null,
            ];
        }
        
        try {
            // 直接获取所有主机的所有活动监控项（不使用 search，在 PHP 中过滤更可靠）
            // 分批处理以避免一次获取太多数据
            $batchSize = 50;
            $hostBatches = array_chunk($hostIds, $batchSize);
            
            $allItems = [];
            
            foreach ($hostBatches as $batchHostIds) {
                // 获取该批次主机的所有监控项
                $items = API::Item()->get([
                    'output' => ['itemid', 'hostid', 'key_', 'name', 'lastvalue', 'value_type'],
                    'hostids' => $batchHostIds,
                    'filter' => [
                        'status' => ITEM_STATUS_ACTIVE
                    ],
                    'preservekeys' => false
                ]);
                
                // 只保留我们关心的监控项
                foreach ($items as $item) {
                    $key = $item['key_'];
                    if (self::isRelevantKey($key)) {
                        $allItems[] = $item;
                    }
                }
            }
            
            // 按主机ID分组处理监控项
            $itemsByHost = [];
            foreach ($allItems as $item) {
                $hostId = $item['hostid'];
                if (!isset($itemsByHost[$hostId])) {
                    $itemsByHost[$hostId] = [];
                }
                $itemsByHost[$hostId][] = $item;
            }
            
            // 为每个主机匹配最佳监控项
            foreach ($hostIds as $hostId) {
                $hostItems = isset($itemsByHost[$hostId]) ? $itemsByHost[$hostId] : [];
                
                // 匹配 CPU 数量
                $cpuItem = self::findBestMatch($hostItems, self::$cpuCountKeys);
                if ($cpuItem && $cpuItem['lastvalue'] !== '') {
                    $results[$hostId]['cpu_total'] = intval($cpuItem['lastvalue']);
                }
                
                // 匹配 CPU 使用率
                $cpuUsageItem = self::findBestMatch($hostItems, self::$cpuUsageKeys);
                if ($cpuUsageItem && $cpuUsageItem['lastvalue'] !== '') {
                    $value = floatval($cpuUsageItem['lastvalue']);
                    // 如果是 idle 值，需要转换
                    if (strpos($cpuUsageItem['key_'], 'idle') !== false) {
                        $value = 100 - $value;
                    }
                    $results[$hostId]['cpu_usage'] = $value;
                }
                
                // 匹配内存总量
                $memItem = self::findBestMatch($hostItems, self::$memoryTotalKeys);
                if ($memItem && $memItem['lastvalue'] !== '') {
                    $value = $memItem['lastvalue'];
                    // Windows WMI 返回的是 KB，需要转换
                    if (strpos($memItem['key_'], 'wmi.get') !== false && strpos($memItem['key_'], 'TotalVisibleMemorySize') !== false) {
                        $value = floatval($value) * 1024; // KB to Bytes
                    }
                    $results[$hostId]['memory_total'] = floatval($value);
                }
                
                // 匹配内存使用率
                $memUsageItem = self::findBestMatch($hostItems, self::$memoryUsageKeys);
                if ($memUsageItem && $memUsageItem['lastvalue'] !== '') {
                    $value = floatval($memUsageItem['lastvalue']);
                    // 如果是 pavailable，需要转换
                    if (strpos($memUsageItem['key_'], 'pavailable') !== false) {
                        $value = 100 - $value;
                    }
                    $results[$hostId]['memory_usage'] = $value;
                }
                
                // 匹配系统名称
                $sysNameItem = self::findBestMatch($hostItems, self::$systemNameKeys);
                if ($sysNameItem && $sysNameItem['lastvalue'] !== '') {
                    $results[$hostId]['system_name'] = $sysNameItem['lastvalue'];
                }
                
                // 匹配操作系统
                $osItem = self::findBestMatch($hostItems, self::$osKeys);
                if ($osItem && $osItem['lastvalue'] !== '') {
                    $results[$hostId]['operating_system'] = self::cleanOsName($osItem['lastvalue']);
                }
                
                // 匹配系统架构
                $archItem = self::findBestMatch($hostItems, self::$archKeys);
                if ($archItem && $archItem['lastvalue'] !== '') {
                    $results[$hostId]['os_architecture'] = $archItem['lastvalue'];
                }
                
                // 匹配内核版本
                $kernelItem = self::findBestMatch($hostItems, self::$kernelKeys);
                if ($kernelItem && $kernelItem['lastvalue'] !== '') {
                    $results[$hostId]['kernel_version'] = self::extractKernelInfo($kernelItem['lastvalue']);
                }
            }
            
        } catch (\Exception $e) {
            error_log('ItemFinder::batchFindItems error: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * 批量计算符合筛选条件的所有主机的 CPU 和内存总量
     * 用于统计显示，不受分页限制
     * 
     * @param array $hostIds 符合条件的所有主机ID
     * @return array ['total_cpu' => int, 'total_memory' => float]
     */
    public static function batchGetCpuMemoryTotals(array $hostIds): array {
        $result = ['total_cpu' => 0, 'total_memory' => 0];
        
        if (empty($hostIds)) {
            return $result;
        }
        
        try {
            // 分批处理
            $batchSize = 100;
            $hostBatches = array_chunk($hostIds, $batchSize);
            
            $cpuByHost = [];
            $memByHost = [];
            
            foreach ($hostBatches as $batchHostIds) {
                // 获取该批次的所有监控项
                $items = API::Item()->get([
                    'output' => ['hostid', 'key_', 'lastvalue'],
                    'hostids' => $batchHostIds,
                    'filter' => ['status' => ITEM_STATUS_ACTIVE],
                    'preservekeys' => false
                ]);
                
                foreach ($items as $item) {
                    $hostId = $item['hostid'];
                    $key = $item['key_'];
                    $value = $item['lastvalue'];
                    
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    
                    // CPU 数量
                    if (!isset($cpuByHost[$hostId]) && strpos($key, 'system.cpu.num') === 0) {
                        $cpuByHost[$hostId] = intval($value);
                    }
                    
                    // 内存总量
                    if (!isset($memByHost[$hostId]) && $key === 'vm.memory.size[total]') {
                        $memByHost[$hostId] = floatval($value);
                    }
                }
            }
            
            $result['total_cpu'] = array_sum($cpuByHost);
            $result['total_memory'] = array_sum($memByHost);
            
        } catch (\Exception $e) {
            error_log('ItemFinder::batchGetCpuMemoryTotals error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * 从监控项列表中找到最佳匹配
     * 
     * @param array $items 监控项列表
     * @param array $keyPatterns 要匹配的 key 模式列表（按优先级排序）
     * @return array|null 匹配的监控项或 null
     */
    private static function findBestMatch(array $items, array $keyPatterns): ?array {
        // 按优先级遍历 key 模式
        foreach ($keyPatterns as $pattern) {
            foreach ($items as $item) {
                // 精确匹配或前缀匹配
                if ($item['key_'] === $pattern || 
                    strpos($item['key_'], rtrim($pattern, '[]')) === 0) {
                    return $item;
                }
            }
        }
        
        // 如果精确匹配失败，尝试模糊匹配
        foreach ($keyPatterns as $pattern) {
            $baseKey = preg_replace('/\[.*\]/', '', $pattern);
            foreach ($items as $item) {
                if (strpos($item['key_'], $baseKey) !== false) {
                    return $item;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查监控项 key 是否是我们关心的类型
     */
    private static function isRelevantKey(string $key): bool {
        $prefixes = [
            'system.cpu',
            'system.hw.cpu',
            'vm.memory',
            'system.hostname',
            'agent.hostname',
            'system.sw',
            'system.hw',
            'system.uname',
            'perf_counter',
            'wmi.get',
            'vfs.file.contents[/etc/os-release]',
            'vfs.file.contents[/proc/version]',
        ];
        
        foreach ($prefixes as $prefix) {
            if (strpos($key, $prefix) === 0 || strpos($key, $prefix) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 清理操作系统名称，提取关键信息
     */
    private static function cleanOsName(string $value): string {
        if (empty($value)) {
            return '-';
        }
        
        // 尝试从 /etc/os-release 格式中提取
        if (strpos($value, 'PRETTY_NAME') !== false) {
            if (preg_match('/PRETTY_NAME="([^"]+)"/', $value, $matches)) {
                return $matches[1];
            }
        }
        
        // 如果太长，截取
        if (strlen($value) > 100) {
            return substr($value, 0, 97) . '...';
        }
        
        return $value;
    }
    
    /**
     * 提取内核版本信息
     */
    public static function extractKernelInfo(string $fullString): string {
        if (empty($fullString)) {
            return '-';
        }

        // 尝试提取 Linux 内核版本
        if (preg_match('/Linux\s+\S+\s+(\S+)/', $fullString, $matches)) {
            return $matches[1];
        }
        
        // 尝试提取 Windows 版本信息
        if (preg_match('/Windows\s+[^0-9]*([0-9]+[^\s,]*)/i', $fullString, $matches)) {
            return 'Windows ' . $matches[1];
        }
        
        // 如果字符串太长，截取
        if (strlen($fullString) > 50) {
            return substr($fullString, 0, 47) . '...';
        }
        
        return $fullString;
    }

    /**
     * 格式化内存大小
     */
    public static function formatMemorySize($bytes): string {
        if (empty($bytes) || !is_numeric($bytes)) {
            return '-';
        }
        
        $bytes = floatval($bytes);
        if ($bytes <= 0) {
            return '-';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * 获取主机接口可用性状态
     * 
     * @param string $hostid 主机ID
     * @param array $interfaces 接口信息数组
     * @return array 状态信息
     */
    public static function getHostAvailabilityStatus($hostid, $interfaces = []): array {
        try {
            if (empty($interfaces)) {
                $interfaces = API::HostInterface()->get([
                    'hostids' => [$hostid],
                    'output' => ['interfaceid', 'type', 'main', 'available', 'error']
                ]);
            }

            if (empty($interfaces)) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // 查找主接口
            $mainInterface = null;
            foreach ($interfaces as $interface) {
                if (isset($interface['main']) && $interface['main'] == 1) {
                    $mainInterface = $interface;
                    break;
                }
            }

            if (!$mainInterface && !empty($interfaces)) {
                $mainInterface = $interfaces[0];
            }

            if (!$mainInterface) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // 根据 available 状态返回
            $available = isset($mainInterface['available']) ? $mainInterface['available'] : '0';
            switch ($available) {
                case '1':
                    return ['status' => 'available', 'text' => 'Available', 'class' => 'status-available'];
                case '2':
                    return ['status' => 'unavailable', 'text' => 'Unavailable', 'class' => 'status-unavailable'];
                default:
                    return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

        } catch (\Exception $e) {
            error_log("ItemFinder::getHostAvailabilityStatus error: " . $e->getMessage());
            return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
        }
    }
    
    // ========== 兼容旧版方法，但建议使用批量方法 ==========
    
    /**
     * 查找单个主机的 CPU 数量（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findCpuCount($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['cpu_total'])) {
            return ['value' => $result[$hostid]['cpu_total']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的内存总量（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findMemoryTotal($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['memory_total'])) {
            return ['value' => $result[$hostid]['memory_total']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的 CPU 使用率（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findCpuUsage($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['cpu_usage'])) {
            return ['value' => $result[$hostid]['cpu_usage']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的内存使用率（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findMemoryUsage($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['memory_usage'])) {
            return ['value' => $result[$hostid]['memory_usage']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的内核版本（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findKernelVersion($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['kernel_version'])) {
            return ['value' => $result[$hostid]['kernel_version']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的系统名称（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findSystemName($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['system_name'])) {
            return ['value' => $result[$hostid]['system_name']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的操作系统（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findOperatingSystem($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['operating_system'])) {
            return ['value' => $result[$hostid]['operating_system']];
        }
        return null;
    }
    
    /**
     * 查找单个主机的操作系统架构（兼容旧代码）
     * @deprecated 请使用 batchFindItems()
     */
    public static function findOsArchitecture($hostid) {
        $result = self::batchFindItems([$hostid]);
        if (isset($result[$hostid]['os_architecture'])) {
            return ['value' => $result[$hostid]['os_architecture']];
        }
        return null;
    }
}
