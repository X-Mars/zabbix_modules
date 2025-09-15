<?php
// 简化的CMDB测试页面
require_once __DIR__ . '/../../include/config.inc.php';
require_once __DIR__ . '/../../include/functions.inc.php';

// 简单的API测试
echo "<h2>主机分组API测试</h2>";

try {
    $hostGroups = API::HostGroup()->get([
        'output' => ['groupid', 'name'],
        'sortfield' => 'name',
        'sortorder' => 'ASC',
        'limit' => 10
    ]);
    
    echo "<p>成功获取到 " . count($hostGroups) . " 个主机分组：</p>";
    echo "<ul>";
    foreach ($hostGroups as $group) {
        echo "<li>{$group['name']} (ID: {$group['groupid']})</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>获取主机分组失败: " . $e->getMessage() . "</p>";
}

echo "<h2>主机搜索API测试</h2>";

try {
    // 测试搜索功能
    $searchTerm = 'test';
    
    // 1. 搜索主机名
    $hosts = API::Host()->get([
        'output' => ['hostid', 'host', 'name'],
        'search' => [
            'host' => $searchTerm,
            'name' => $searchTerm
        ],
        'searchWildcardsEnabled' => true,
        'searchByAny' => true,
        'limit' => 5
    ]);
    
    echo "<p>主机名搜索 '$searchTerm' 结果：" . count($hosts) . " 个主机</p>";
    foreach ($hosts as $host) {
        echo "<li>{$host['name']} ({$host['host']})</li>";
    }
    
    // 2. 搜索IP地址
    $interfaces = API::HostInterface()->get([
        'output' => ['hostid', 'ip'],
        'search' => ['ip' => '192.168'],
        'searchWildcardsEnabled' => true,
        'limit' => 5
    ]);
    
    echo "<p>IP搜索 '192.168' 结果：" . count($interfaces) . " 个接口</p>";
    foreach ($interfaces as $interface) {
        echo "<li>主机ID: {$interface['hostid']}, IP: {$interface['ip']}</li>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>搜索测试失败: " . $e->getMessage() . "</p>";
}
?>
