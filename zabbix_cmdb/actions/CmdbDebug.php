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
        // 移除 disableSIDValidation() 调用，因为在某些版本中不存在
    }
    
    protected function checkInput() {
        return true;
    }
    
    protected function checkPermissions() {
        return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
    }
    
    protected function doAction() {
        echo "<h1>CMDB调试信息</h1>";
        
        // 1. 测试主机分组获取 - 多种方法
        echo "<h2>1. 主机分组获取测试</h2>";
        
        // 方法1：标准API调用
        echo "<h3>方法1：标准hostgroup.get</h3>";
        try {
            $hostGroups1 = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
            echo "<p style='color: green;'>✓ 成功获取到 " . count($hostGroups1) . " 个主机分组</p>";
            foreach (array_slice($hostGroups1, 0, 5) as $group) {
                echo "<li>{$group['name']} (ID: {$group['groupid']})</li>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ 标准方法失败: " . $e->getMessage() . "</p>";
        }
        
        // 方法2：只获取包含主机的分组
        echo "<h3>方法2：with_hosts=true</h3>";
        try {
            $hostGroups2 = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'with_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
            echo "<p style='color: green;'>✓ 成功获取到 " . count($hostGroups2) . " 个包含主机的分组</p>";
            foreach (array_slice($hostGroups2, 0, 5) as $group) {
                echo "<li>{$group['name']} (ID: {$group['groupid']})</li>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ with_hosts方法失败: " . $e->getMessage() . "</p>";
        }
        
        // 方法3：通过主机获取分组
        echo "<h3>方法3：通过主机获取分组</h3>";
        try {
            $hosts = API::Host()->get([
                'output' => ['hostid'],
                'selectGroups' => ['groupid', 'name'],
                'limit' => 100
            ]);
            
            $groupsMap = [];
            foreach ($hosts as $host) {
                foreach ($host['groups'] as $group) {
                    $groupsMap[$group['groupid']] = $group;
                }
            }
            
            $hostGroups3 = array_values($groupsMap);
            echo "<p style='color: green;'>✓ 通过 " . count($hosts) . " 个主机获取到 " . count($hostGroups3) . " 个分组</p>";
            foreach (array_slice($hostGroups3, 0, 5) as $group) {
                echo "<li>{$group['name']} (ID: {$group['groupid']})</li>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ 通过主机获取失败: " . $e->getMessage() . "</p>";
        }
        
        // 方法4：测试不同的输出参数
        echo "<h3>方法4：extend输出</h3>";
        try {
            $hostGroups4 = API::HostGroup()->get([
                'output' => 'extend',
                'limit' => 5
            ]);
            echo "<p style='color: green;'>✓ extend输出获取到 " . count($hostGroups4) . " 个分组</p>";
            foreach ($hostGroups4 as $group) {
                echo "<li>{$group['name']} (ID: {$group['groupid']}) - Internal: {$group['internal']}</li>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ extend输出失败: " . $e->getMessage() . "</p>";
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
