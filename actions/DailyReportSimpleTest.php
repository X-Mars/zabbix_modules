<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

class DailyReportSimpleTest extends CController {

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
        
        // 获取第一个主机进行测试
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 1
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "没有找到监控的主机。\n";
            exit;
        }
        
        $host = $hosts[0];
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo "=== 简单监控项测试 ===\n\n";
        echo "测试主机: {$host['name']} (ID: {$host['hostid']})\n\n";
        
        // 搜索所有可能的CPU和内存相关监控项
        echo "=== 搜索CPU相关监控项 ===\n";
        $this->searchItems($host['hostid'], ['cpu', 'processor', 'core']);
        
        echo "\n=== 搜索内存相关监控项 ===\n";
        $this->searchItems($host['hostid'], ['memory', 'mem', 'ram']);
        
        echo "\n=== 使用通配符搜索 ===\n";
        $this->wildcardSearch($host['hostid'], 'util');
        
        echo "\n=== 按键值搜索 ===\n";
        $this->keySearch($host['hostid'], ['system.cpu', 'vm.memory']);
        
        exit;
    }
    
    private function searchItems($hostid, $keywords) {
        foreach ($keywords as $keyword) {
            echo "搜索关键词: {$keyword}\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['name' => $keyword],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  未找到匹配的监控项\n";
            } else {
                foreach ($items as $item) {
                    echo "  - {$item['name']} (键值: {$item['key_']}, 单位: {$item['units']})\n";
                }
            }
            echo "\n";
        }
    }
    
    private function wildcardSearch($hostid, $keyword) {
        echo "通配符搜索: *{$keyword}*\n";
        
        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'key_', 'units'],
            'hostids' => $hostid,
            'filter' => ['status' => ITEM_STATUS_ACTIVE],
            'search' => ['name' => $keyword],
            'searchWildcardsEnabled' => true,
            'limit' => 20
        ]);
        
        if (empty($items)) {
            echo "  未找到匹配的监控项\n";
        } else {
            foreach ($items as $item) {
                echo "  - {$item['name']} (键值: {$item['key_']}, 单位: {$item['units']})\n";
            }
        }
        echo "\n";
    }
    
    private function keySearch($hostid, $keyPatterns) {
        foreach ($keyPatterns as $pattern) {
            echo "键值模式搜索: {$pattern}\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['key_' => $pattern],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  未找到匹配的监控项\n";
            } else {
                foreach ($items as $item) {
                    echo "  - {$item['name']} (键值: {$item['key_']}, 单位: {$item['units']})\n";
                }
            }
            echo "\n";
        }
    }
}
