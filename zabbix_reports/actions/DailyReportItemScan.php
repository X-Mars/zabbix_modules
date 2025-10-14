<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixReports\Lib\ItemFinder;

class DailyReportItemScan extends CController {

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
        
        // 获取所有主机
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 5  // 只检查前5个主机
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "没有找到监控的主机。\n";
            exit;
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo "=== Zabbix 监控项扫描工具 ===\n\n";
        echo "正在扫描前5个主机的所有监控项...\n\n";
        
        foreach ($hosts as $host) {
            echo "主机: {$host['name']} (ID: {$host['hostid']})\n";
            echo str_repeat("=", 60) . "\n";
            
            // 获取所有监控项
            $allItems = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units', 'value_type'],
                'hostids' => $host['hostid'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'sortfield' => 'name'
            ]);
            
            echo "总监控项数: " . count($allItems) . "\n\n";
            
            // 分类监控项
            $categories = [
                'CPU相关' => [],
                '内存相关' => [],
                '网络相关' => [],
                '磁盘相关' => [],
                '系统相关' => [],
                '其他' => []
            ];
            
            foreach ($allItems as $item) {
                $name = strtolower($item['name']);
                $key = strtolower($item['key_']);
                
                if (strpos($name, 'cpu') !== false || strpos($key, 'cpu') !== false) {
                    $categories['CPU相关'][] = $item;
                } elseif (strpos($name, 'mem') !== false || strpos($key, 'mem') !== false) {
                    $categories['内存相关'][] = $item;
                } elseif (strpos($name, 'net') !== false || strpos($key, 'net') !== false || 
                         strpos($name, 'interface') !== false) {
                    $categories['网络相关'][] = $item;
                } elseif (strpos($name, 'disk') !== false || strpos($key, 'disk') !== false ||
                         strpos($name, 'file') !== false || strpos($key, 'vfs') !== false) {
                    $categories['磁盘相关'][] = $item;
                } elseif (strpos($name, 'system') !== false || strpos($key, 'system') !== false ||
                         strpos($name, 'uptime') !== false || strpos($name, 'load') !== false) {
                    $categories['系统相关'][] = $item;
                } else {
                    $categories['其他'][] = $item;
                }
            }
            
            // 输出每个分类
            foreach ($categories as $categoryName => $items) {
                if (!empty($items)) {
                    echo "{$categoryName} (" . count($items) . " 项):\n";
                    foreach ($items as $item) {
                        echo "  - {$item['name']}\n";
                        echo "    键值: {$item['key_']}\n";
                        echo "    单位: {$item['units']}\n";
                        echo "    类型: " . $this->getValueTypeName($item['value_type']) . "\n\n";
                    }
                }
            }
            
            echo str_repeat("=", 80) . "\n\n";
        }
        
        echo "=== 搜索模式建议 ===\n";
        echo "基于扫描结果，建议以下搜索模式:\n\n";
        echo "CPU使用率可能的名称:\n";
        echo "- CPU utilization\n";
        echo "- CPU usage\n";
        echo "- Processor time\n";
        echo "- CPU idle time\n\n";
        
        echo "CPU数量可能的名称:\n";
        echo "- Number of CPUs\n";
        echo "- CPU cores\n";
        echo "- Processors\n\n";
        
        echo "内存使用率可能的名称:\n";
        echo "- Memory utilization\n";
        echo "- Memory usage\n";
        echo "- Available memory\n\n";
        
        echo "内存总量可能的名称:\n";
        echo "- Total memory\n";
        echo "- Physical memory\n";
        echo "- Memory size\n";
        
        exit;
    }
    
    private function getValueTypeName($valueType) {
        $types = [
            ITEM_VALUE_TYPE_FLOAT => 'Float',
            ITEM_VALUE_TYPE_STR => 'Character',
            ITEM_VALUE_TYPE_LOG => 'Log',
            ITEM_VALUE_TYPE_UINT64 => 'Unsigned integer',
            ITEM_VALUE_TYPE_TEXT => 'Text'
        ];
        
        return isset($types[$valueType]) ? $types[$valueType] : 'Unknown';
    }
}
