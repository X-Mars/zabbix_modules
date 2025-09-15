<?php
// 调试版本的CMDB控制器
namespace Modules\ZabbixCmdb\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use API;
use Exception;
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class CmdbDebug extends CController {
    
    public function init() {
        $this->disableSIDValidation();
    }
    
    protected function checkInput() {
        return true;
    }
    
    protected function checkPermissions() {
        return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
    }
    
    protected function doAction() {
        echo "<h1>CMDB调试信息</h1>";
        
        // 1. 测试主机分组获取
        echo "<h2>1. 主机分组获取测试</h2>";
        $hostGroups = [];
        try {
            $hostGroups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
            echo "<p style='color: green;'>✓ 成功获取到 " . count($hostGroups) . " 个主机分组</p>";
            echo "<ul>";
            foreach (array_slice($hostGroups, 0, 10) as $group) {
                echo "<li>{$group['name']} (ID: {$group['groupid']})</li>";
            }
            if (count($hostGroups) > 10) {
                echo "<li>... 还有 " . (count($hostGroups) - 10) . " 个分组</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ 获取主机分组失败: " . $e->getMessage() . "</p>";
        }
        
        // 2. 测试主机获取
        echo "<h2>2. 主机获取测试</h2>";
        try {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'status'],
                'selectHostGroups' => ['groupid', 'name'],
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
                'sortfield' => 'host',
                'sortorder' => 'ASC',
                'limit' => 10
            ]);
            echo "<p style='color: green;'>✓ 成功获取到 " . count($hosts) . " 个主机</p>";
            echo "<ul>";
            foreach ($hosts as $host) {
                $groups = array_column($host['hostgroups'], 'name');
                echo "<li>{$host['name']} ({$host['host']}) - 分组: " . implode(', ', $groups) . "</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ 获取主机失败: " . $e->getMessage() . "</p>";
        }
        
        // 3. 测试搜索功能
        echo "<h2>3. 搜索功能测试</h2>";
        $searchTerm = 'test';
        echo "<p>搜索关键词: '$searchTerm'</p>";
        
        try {
            // 主机名搜索
            $nameHosts = API::Host()->get([
                'output' => ['hostid', 'host', 'name'],
                'search' => [
                    'host' => '*' . $searchTerm . '*',
                    'name' => '*' . $searchTerm . '*'
                ],
                'searchWildcardsEnabled' => true,
                'searchByAny' => true,
                'limit' => 5
            ]);
            echo "<p style='color: green;'>✓ 主机名搜索结果: " . count($nameHosts) . " 个主机</p>";
            
            // IP搜索
            $interfaces = API::HostInterface()->get([
                'output' => ['hostid', 'ip'],
                'search' => ['ip' => '192.168*'],
                'searchWildcardsEnabled' => true,
                'limit' => 5
            ]);
            echo "<p style='color: green;'>✓ IP搜索结果: " . count($interfaces) . " 个接口</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ 搜索测试失败: " . $e->getMessage() . "</p>";
        }
        
        // 4. 测试用户权限
        echo "<h2>4. 用户权限测试</h2>";
        $user = CWebUser::$data;
        echo "<p>当前用户: {$user['username']} (ID: {$user['userid']})</p>";
        echo "<p>用户类型: " . $this->getUserType() . "</p>";
        
        // 5. 生成下拉框HTML测试
        echo "<h2>5. 下拉框HTML生成测试</h2>";
        $groupOptions = '<option value="0">所有分组</option>';
        foreach (array_slice($hostGroups, 0, 5) as $group) {
            $groupOptions .= '<option value="' . $group['groupid'] . '">' . htmlspecialchars($group['name']) . '</option>';
        }
        echo "<p>生成的HTML选项:</p>";
        echo "<pre>" . htmlspecialchars($groupOptions) . "</pre>";
        
        echo "<h2>调试完成</h2>";
        echo "<p><a href='zabbix.php?action=cmdb.view'>返回CMDB页面</a></p>";
        
        exit;
    }
}
