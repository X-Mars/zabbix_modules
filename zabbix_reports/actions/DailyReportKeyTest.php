<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixReports\Lib\ItemFinder;

class DailyReportKeyTest extends CController {

    public function init(): void {
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
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        
        // 获取第一个主机进行测试
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 3  // 测试前3个主机
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "没有找到监控的主机。\n";
            exit;
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo "=== 监控项Key测试工具 ===\n\n";
        echo "测试指定的监控项Key:\n";
        echo "- system.cpu.num (CPU内核总数)\n";
        echo "- system.cpu.util (CPU使用率)\n";
        echo "- vm.memory.size[total] (内存总量)\n";
        echo "- vm.memory.utilization (内存使用率)\n\n";
        
        $yesterday = strtotime('yesterday');
        $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        
        foreach ($hosts as $host) {
            echo "主机: {$host['name']} (ID: {$host['hostid']})\n";
            echo str_repeat("=", 60) . "\n";
            
            // 测试每个具体的Key
            $this->testSpecificKey($host['hostid'], 'system.cpu.num', 'CPU内核总数');
            $this->testSpecificKey($host['hostid'], 'system.cpu.util', 'CPU使用率');
            $this->testSpecificKey($host['hostid'], 'vm.memory.size[total]', '内存总量');
            $this->testSpecificKey($host['hostid'], 'vm.memory.utilization', '内存使用率');
            
            echo "\n=== 使用ItemFinder测试 ===\n";
            
            // 使用ItemFinder测试
            $cpuUtil = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            $cpuCount = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            $memUtil = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
            $memTotal = ItemFinder::findMemoryTotal($host['hostid'], $from, $till);
            
            echo "CPU使用率: " . ($cpuUtil ? 
                "找到项目 {$cpuUtil['item']['name']} (key: {$cpuUtil['item']['key_']}) = " . 
                ($cpuUtil['value'] !== null ? number_format($cpuUtil['value'], 2) . '%' : 'N/A') : 'N/A') . "\n";
                
            echo "CPU数量: " . ($cpuCount ? 
                "找到项目 {$cpuCount['item']['name']} (key: {$cpuCount['item']['key_']}) = " . 
                ($cpuCount['value'] !== null ? $cpuCount['value'] : 'N/A') : 'N/A') . "\n";
                
            echo "内存使用率: " . ($memUtil ? 
                "找到项目 {$memUtil['item']['name']} (key: {$memUtil['item']['key_']}) = " . 
                ($memUtil['value'] !== null ? number_format($memUtil['value'], 2) . '%' : 'N/A') : 'N/A') . "\n";
                
            echo "内存总量: " . ($memTotal ? 
                "找到项目 {$memTotal['item']['name']} (key: {$memTotal['item']['key_']}) = " . 
                ($memTotal['value'] !== null ? number_format($memTotal['value'] / (1024*1024*1024), 2) . ' GB' : 'N/A') : 'N/A') . "\n";
            
            echo "\n" . str_repeat("-", 80) . "\n\n";
        }
        
        exit;
    }
    
    private function testSpecificKey($hostid, $key, $description) {
        echo "测试 {$description} (key: {$key}):\n";
        
        // 精确匹配key
        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'units'],
            'hostids' => $hostid,
            'filter' => [
                'status' => ITEM_STATUS_ACTIVE,
                'key_' => $key
            ]
        ]);
        
        if (empty($items)) {
            echo "  未找到精确匹配的监控项\n";
            
            // 尝试模糊匹配
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['key_' => $key],
                'searchWildcardsEnabled' => true
            ]);
            
            if (!empty($items)) {
                echo "  找到模糊匹配的监控项:\n";
                foreach ($items as $item) {
                    echo "    - {$item['name']} (key: {$item['key_']}, 最后值: {$item['lastvalue']}, 单位: {$item['units']})\n";
                }
            } else {
                echo "  未找到任何匹配的监控项\n";
            }
        } else {
            $item = $items[0];
            echo "  找到监控项: {$item['name']}\n";
            echo "  键值: {$item['key_']}\n";
            echo "  最后值: {$item['lastvalue']}\n";
            echo "  单位: {$item['units']}\n";
            echo "  最后更新: " . date('Y-m-d H:i:s', $item['lastclock']) . "\n";
            
            // 尝试获取历史数据
            $yesterday = strtotime('yesterday');
            $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            
            $history = API::History()->get([
                'output' => ['value', 'clock'],
                'itemids' => $item['itemid'],
                'time_from' => $from,
                'time_till' => $till,
                'sortfield' => 'clock',
                'sortorder' => 'DESC',
                'limit' => 5
            ]);
            
            if (!empty($history)) {
                echo "  昨天的历史数据 (最近5条):\n";
                foreach ($history as $h) {
                    echo "    " . date('Y-m-d H:i:s', $h['clock']) . " = {$h['value']}\n";
                }
            } else {
                echo "  没有找到昨天的历史数据\n";
            }
        }
        echo "\n";
    }
}
