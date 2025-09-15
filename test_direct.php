<?php
// 模拟Zabbix环境测试
echo "<html><head><title>CMDB Direct Test</title></head><body>";
echo "<h1>CMDB Direct HTML Test</h1>";

// 模拟数据
$data = [
    'host_groups' => [
        ['groupid' => 2, 'name' => 'Linux servers'],
        ['groupid' => 4, 'name' => 'Nerwork'],
        ['groupid' => 5, 'name' => 'Zabbix servers']
    ],
    'selected_groupid' => 0,
    'search' => ''
];

// 构建下拉框选项
$groupOptions = '<option value="0">All Groups</option>';
foreach ($data['host_groups'] as $group) {
    $selected = ($group['groupid'] == $data['selected_groupid']) ? ' selected' : '';
    $groupOptions .= '<option value="' . $group['groupid'] . '"' . $selected . '>' . $group['name'] . '</option>';
}

echo "<h2>Debug Info:</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
echo "DEBUG: Received " . count($data['host_groups']) . " host groups from controller<br>";
echo "Group names: " . implode(', ', array_column($data['host_groups'], 'name'));
echo "</div>";

echo "<h2>Form Test:</h2>";
echo "<form method='get' action='test.php'>";
echo "<div style='margin: 10px 0;'>";
echo "<label>Search: <input type='text' name='search' value='" . htmlspecialchars($data['search']) . "'></label>";
echo "</div>";
echo "<div style='margin: 10px 0;'>";
echo "<label>Select host group: <select name='groupid' id='groupid-select'>$groupOptions</select></label>";
echo "</div>";
echo "<button type='button' onclick='clearFilters()'>Clear</button>";
echo "<button type='submit'>Search</button>";
echo "</form>";

echo "<h2>Raw HTML:</h2>";
echo "<pre>" . htmlspecialchars("<select name='groupid' id='groupid-select'>$groupOptions</select>") . "</pre>";

echo "<script>
function clearFilters() {
    var searchInput = document.querySelector('input[name=\"search\"]');
    var groupSelect = document.getElementById('groupid-select');
    var form = document.querySelector('form');
    
    console.log('Clear button clicked');
    console.log('Search input:', searchInput);
    console.log('Group select:', groupSelect);
    console.log('Form:', form);
    
    if (searchInput) searchInput.value = '';
    if (groupSelect) groupSelect.value = '0';
    if (form) form.submit();
}
</script>";

echo "</body></html>";
?>
