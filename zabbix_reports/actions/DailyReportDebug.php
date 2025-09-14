<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixReports\Lib\ItemFinder;

require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
use Modules\ZabbixReports\Lib\PdfGenerator;

class DailyReportDebug extends CController {

    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $yesterday = strtotime('yesterday');
        $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));

        // 获取一个主机来测试监控项
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 3
        ]);

        $debugInfo = [];
        
        foreach ($hosts as $host) {
            $hostDebug = [
                'hostid' => $host['hostid'],
                'hostname' => $host['name'],
                'items' => []
            ];
            
            // 获取该主机的所有监控项
            $allItems = API::Item()->get([
                'output' => ['itemid', 'name', 'key_'],
                'hostids' => $host['hostid'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'limit' => 50
            ]);
            
            $hostDebug['total_items'] = count($allItems);
            
            // 查找CPU相关监控项
            $cpuItems = [];
            $memItems = [];
            
            foreach ($allItems as $item) {
                if (stripos($item['name'], 'cpu') !== false || stripos($item['key_'], 'cpu') !== false) {
                    $cpuItems[] = [
                        'name' => $item['name'],
                        'key' => $item['key_'],
                        'itemid' => $item['itemid']
                    ];
                }
                
                if (stripos($item['name'], 'mem') !== false || stripos($item['key_'], 'mem') !== false) {
                    $memItems[] = [
                        'name' => $item['name'],
                        'key' => $item['key_'],
                        'itemid' => $item['itemid']
                    ];
                }
            }
            
            $hostDebug['cpu_items'] = $cpuItems;
            $hostDebug['memory_items'] = $memItems;
            
            // 测试ItemFinder
            $yesterday = strtotime('yesterday');
            $testFrom = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            $testTill = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            
            $hostDebug['itemfinder_results'] = [
                'cpu_utilization' => ItemFinder::findCpuUtilization($host['hostid'], $testFrom, $testTill),
                'cpu_count' => ItemFinder::findCpuCount($host['hostid'], $testFrom, $testTill),
                'memory_utilization' => ItemFinder::findMemoryUtilization($host['hostid'], $testFrom, $testTill),
                'memory_total' => ItemFinder::findMemoryTotal($host['hostid'], $testFrom, $testTill)
            ];
            
            $debugInfo[] = $hostDebug;
        }
        
        // 输出调试信息
        header('Content-Type: text/plain; charset=UTF-8');
        echo "=== Zabbix 监控项调试信息 ===\n\n";
        echo "时间范围: " . date('Y-m-d H:i:s', $from) . " 到 " . date('Y-m-d H:i:s', $till) . "\n\n";
        
        foreach ($debugInfo as $hostInfo) {
            echo "主机: {$hostInfo['hostname']} (ID: {$hostInfo['hostid']})\n";
            echo "总监控项数: {$hostInfo['total_items']}\n\n";
            
            echo "CPU相关监控项:\n";
            if (empty($hostInfo['cpu_items'])) {
                echo "  没有找到CPU相关监控项\n";
            } else {
                foreach ($hostInfo['cpu_items'] as $item) {
                    echo "  - 名称: {$item['name']}\n";
                    echo "    键值: {$item['key']}\n";
                    echo "    ID: {$item['itemid']}\n\n";
                }
            }
            
            echo "内存相关监控项:\n";
            if (empty($hostInfo['memory_items'])) {
                echo "  没有找到内存相关监控项\n";
            } else {
                foreach ($hostInfo['memory_items'] as $item) {
                    echo "  - 名称: {$item['name']}\n";
                    echo "    键值: {$item['key']}\n";
                    echo "    ID: {$item['itemid']}\n\n";
                }
            }
            
            echo str_repeat("-", 80) . "\n";
            
            echo "ItemFinder 测试结果:\n";
            foreach ($hostInfo['itemfinder_results'] as $type => $result) {
                echo "  {$type}: ";
                if ($result) {
                    echo "找到 - 名称: {$result['item']['name']}, 键值: {$result['item']['key_']}, 值: " . 
                         ($result['value'] !== null ? $result['value'] : 'N/A') . "\n";
                } else {
                    echo "未找到\n";
                }
            }
            
            echo str_repeat("=", 80) . "\n\n";
        }
        
        echo "=== 监控项搜索建议 ===\n";
        echo "根据以上输出，您可以看到实际的监控项名称和键值。\n";
        echo "请查找包含以下关键词的监控项:\n";
        echo "- CPU使用率: 'utilization', 'usage', 'util'\n";
        echo "- CPU总数: 'number', 'cores', 'count'\n";
        echo "- 内存使用率: 'utilization', 'usage', 'util'\n";
        echo "- 内存总量: 'total', 'size'\n";
        
        exit;
    }
}
