<?php
// 测试主机分组API调用

require_once __DIR__ . '/zabbix_cmdb/lib/LanguageManager.php';

echo "正在测试主机分组API调用...\n\n";

try {
    // 方法1：标准API调用
    echo "方法1：标准host group API调用\n";
    $hostGroups = API::HostGroup()->get([
        'output' => ['groupid', 'name'],
        'sortfield' => 'name',
        'sortorder' => 'ASC'
    ]);
    echo "获取到的主机分组数量: " . count($hostGroups) . "\n";
    foreach ($hostGroups as $group) {
        echo "- {$group['name']} (ID: {$group['groupid']})\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "方法1失败: " . $e->getMessage() . "\n\n";
    
    try {
        // 方法2：通过用户组权限获取
        echo "方法2：通过用户组权限获取\n";
        $user = CWebUser::$data;
        $userGroups = API::UserGroup()->get([
            'output' => ['usrgrpid'],
            'userids' => $user['userid'],
            'selectRights' => ['permission', 'id']
        ]);
        
        $groupIds = [];
        foreach ($userGroups as $userGroup) {
            if (!empty($userGroup['rights'])) {
                foreach ($userGroup['rights'] as $right) {
                    if ($right['permission'] >= PERM_READ) {
                        $groupIds[] = $right['id'];
                    }
                }
            }
        }
        
        if (!empty($groupIds)) {
            $hostGroups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'groupids' => array_unique($groupIds),
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
            echo "获取到的主机分组数量: " . count($hostGroups) . "\n";
            foreach ($hostGroups as $group) {
                echo "- {$group['name']} (ID: {$group['groupid']})\n";
            }
        } else {
            echo "用户没有主机分组权限\n";
        }
        echo "\n";
    } catch (Exception $e2) {
        echo "方法2失败: " . $e2->getMessage() . "\n\n";
        
        try {
            // 方法3：通过主机获取分组
            echo "方法3：通过主机获取分组\n";
            $hosts = API::Host()->get([
                'output' => ['hostid'],
                'selectGroups' => ['groupid', 'name'],
                'limit' => 50
            ]);
            
            $allGroups = [];
            foreach ($hosts as $host) {
                foreach ($host['groups'] as $group) {
                    $allGroups[$group['groupid']] = $group;
                }
            }
            
            $hostGroups = array_values($allGroups);
            usort($hostGroups, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            
            echo "获取到的主机分组数量: " . count($hostGroups) . "\n";
            foreach ($hostGroups as $group) {
                echo "- {$group['name']} (ID: {$group['groupid']})\n";
            }
        } catch (Exception $e3) {
            echo "方法3失败: " . $e3->getMessage() . "\n";
        }
    }
}

echo "\n测试完成。\n";
