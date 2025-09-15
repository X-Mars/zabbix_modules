<?php
// 简单的CMDB测试页面 - 直接测试数据流
echo "<html><head><title>CMDB Test</title></head><body>";
echo "<h1>CMDB Data Flow Test</h1>";

// 模拟控制器数据
$testData = [
    'host_groups' => [
        ['groupid' => 2, 'name' => 'Linux servers'],
        ['groupid' => 4, 'name' => 'Nerwork'],
        ['groupid' => 5, 'name' => 'Zabbix servers']
    ],
    'selected_groupid' => 0,
    'search' => '',
    'hosts' => [
        ['hostid' => 1, 'name' => 'Test Host', 'groups' => [['groupid' => 2, 'name' => 'Linux servers']]]
    ]
];

// 构建下拉框选项
$groupOptions = '<option value="0">All Groups</option>';
foreach ($testData['host_groups'] as $group) {
    $selected = ($group['groupid'] == $testData['selected_groupid']) ? ' selected' : '';
    $groupOptions .= '<option value="' . $group['groupid'] . '"' . $selected . '>' . $group['name'] . '</option>';
}

echo "<h2>Generated HTML Select:</h2>";
echo "<select name='groupid'>$groupOptions</select>";

echo "<h2>Raw Options HTML:</h2>";
echo "<pre>" . htmlspecialchars($groupOptions) . "</pre>";

echo "<h2>Test Data:</h2>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

echo "</body></html>";
?>
