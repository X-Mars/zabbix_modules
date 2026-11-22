<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

use Modules\ZabbixReports\Lib\LanguageManager;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';

class DailyReportSimpleTest extends CController {

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
            'limit' => 1
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo LanguageManager::t('没有找到监控的主机。') . "\n";
            exit;
        }
        
        $host = $hosts[0];
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo LanguageManager::t('=== 简单监控项测试 ===') . "\n\n";
        echo LanguageManager::tf('测试主机: %s (ID: %s)', $host['name'], $host['hostid']) . "\n\n";
        
        // 搜索所有可能的CPU和内存相关监控项
        echo LanguageManager::t('=== 搜索CPU相关监控项 ===') . "\n";
        $this->searchItems($host['hostid'], ['cpu', 'processor', 'core']);
        
        echo "\n" . LanguageManager::t('=== 搜索内存相关监控项 ===') . "\n";
        $this->searchItems($host['hostid'], ['memory', 'mem', 'ram']);
        
        echo "\n" . LanguageManager::t('=== 使用通配符搜索 ===') . "\n";
        $this->wildcardSearch($host['hostid'], 'util');
        
        echo "\n" . LanguageManager::t('=== 按键值搜索 ===') . "\n";
        $this->keySearch($host['hostid'], ['system.cpu', 'vm.memory']);
        
        exit;
    }
    
    private function searchItems($hostid, $keywords) {
        foreach ($keywords as $keyword) {
            echo LanguageManager::tf('搜索关键词: %s', $keyword) . "\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['name' => $keyword],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  " . LanguageManager::t('未找到匹配的监控项') . "\n";
            } else {
                foreach ($items as $item) {
                    echo "  " . LanguageManager::tf('- %s (键值: %s, 单位: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
                }
            }
            echo "\n";
        }
    }
    
    private function wildcardSearch($hostid, $keyword) {
        echo LanguageManager::tf('通配符搜索: *%s*', $keyword) . "\n";
        
        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'key_', 'units'],
            'hostids' => $hostid,
            'filter' => ['status' => ITEM_STATUS_ACTIVE],
            'search' => ['name' => $keyword],
            'searchWildcardsEnabled' => true,
            'limit' => 20
        ]);
        
        if (empty($items)) {
            echo "  " . LanguageManager::t('未找到匹配的监控项') . "\n";
        } else {
            foreach ($items as $item) {
                echo "  " . LanguageManager::tf('- %s (键值: %s, 单位: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
            }
        }
        echo "\n";
    }
    
    private function keySearch($hostid, $keyPatterns) {
        foreach ($keyPatterns as $pattern) {
            echo LanguageManager::tf('键值模式搜索: %s', $pattern) . "\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['key_' => $pattern],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  " . LanguageManager::t('未找到匹配的监控项') . "\n";
            } else {
                foreach ($items as $item) {
                    echo "  " . LanguageManager::tf('- %s (键值: %s, 单位: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
                }
            }
            echo "\n";
        }
    }
}
