<?php
// 简单的主机分组API测试
require_once '/usr/share/zabbix/include/config.inc.php';
require_once '/usr/share/zabbix/include/functions.inc.php';

echo "测试主机分组API调用\n\n";

// 测试1：标准调用
echo "1. 标准hostgroup.get调用:\n";
try {
    $groups1 = API::HostGroup()->get([
        'output' => ['groupid', 'name'],
        'sortfield' => 'name',
        'sortorder' => 'ASC',
        'limit' => 10
    ]);
    echo "成功获取 " . count($groups1) . " 个分组\n";
    foreach ($groups1 as $group) {
        echo "- {$group['name']} (ID: {$group['groupid']})\n";
    }
} catch (Exception $e) {
    echo "失败: " . $e->getMessage() . "\n";
}

echo "\n2. 只获取包含主机的分组:\n";
try {
    $groups2 = API::HostGroup()->get([
        'output' => ['groupid', 'name'],
        'with_hosts' => true,
        'sortfield' => 'name',
        'sortorder' => 'ASC',
        'limit' => 10
    ]);
    echo "成功获取 " . count($groups2) . " 个包含主机的分组\n";
    foreach ($groups2 as $group) {
        echo "- {$group['name']} (ID: {$group['groupid']})\n";
    }
} catch (Exception $e) {
    echo "失败: " . $e->getMessage() . "\n";
}

echo "\n3. 通过主机获取分组:\n";
try {
    $hosts = API::Host()->get([
        'output' => ['hostid'],
        'selectGroups' => ['groupid', 'name'],
        'limit' => 5
    ]);
    
    $allGroups = [];
    foreach ($hosts as $host) {
        foreach ($host['groups'] as $group) {
            $allGroups[$group['groupid']] = $group;
        }
    }
    
    echo "通过 " . count($hosts) . " 个主机获取到 " . count($allGroups) . " 个分组\n";
    foreach (array_slice($allGroups, 0, 10) as $group) {
        echo "- {$group['name']} (ID: {$group['groupid']})\n";
    }
} catch (Exception $e) {
    echo "失败: " . $e->getMessage() . "\n";
}

echo "\n测试完成。\n";
?>
