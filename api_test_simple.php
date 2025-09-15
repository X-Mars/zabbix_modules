<?php
// API测试脚本 - 不依赖完整Zabbix环境
echo "Zabbix 7.0 API参数测试\n\n";

// 测试不同的参数组合
$testCases = [
    "标准主机分组获取" => [
        'method' => 'hostgroup.get',
        'params' => [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]
    ],
    "包含主机的分组" => [
        'method' => 'hostgroup.get', 
        'params' => [
            'output' => ['groupid', 'name'],
            'with_hosts' => true,
            'sortfield' => 'name'
        ]
    ],
    "主机查询（新API）" => [
        'method' => 'host.get',
        'params' => [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
            'limit' => 5
        ]
    ]
];

foreach ($testCases as $name => $test) {
    echo "测试: $name\n";
    echo "方法: {$test['method']}\n";
    echo "参数: " . json_encode($test['params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "预期返回结构:\n";
    
    if ($test['method'] === 'hostgroup.get') {
        echo "- 数组，每个元素包含 groupid, name\n";
    } else {
        echo "- 数组，每个主机包含 hostgroups 或 groups 键\n";
    }
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "根据Zabbix 7.0文档:\n";
echo "1. selectHostGroups 参数应该返回 groups 数组\n";
echo "2. 不应使用已弃用的 selectGroups 参数\n";
echo "3. 主机分组数据在主机对象的 groups 键中\n";
echo "4. 搜索应使用 searchWildcardsEnabled: true\n\n";

echo "测试完成。请在实际Zabbix环境中验证这些参数。\n";
?>
