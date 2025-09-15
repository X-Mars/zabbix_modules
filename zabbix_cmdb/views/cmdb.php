<?php

// 引入语言管理器
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;

// 创建页面标题
$pageTitle = LanguageManager::t('CMDB');

// 构建下拉框选项
$groupOptions = '<option value="0">' . LanguageManager::t('All Groups') . '</option>';
foreach ($data['host_groups'] as $group) {
    $selected = ($group['groupid'] == $data['selected_groupid']) ? ' selected' : '';
    $groupOptions .= '<option value="' . $group['groupid'] . '"' . $selected . '>' . htmlspecialchars($group['name']) . '</option>';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?></title>
    <style>
.cmdb-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}
.cmdb-header {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}
.search-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}
.search-form .form-field {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}
.search-form .form-field label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #495057;
}
.search-form .form-field input,
.search-form .form-field select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
.search-form .form-field input:focus,
.search-form .form-field select:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
.btn-search {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}
.btn-search:hover {
    background-color: #0056b3;
}
.btn-clear {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}
.btn-clear:hover {
    background-color: #545b62;
}
.hosts-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow: hidden;
}
.hosts-table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: bold;
    padding: 12px 8px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-size: 14px;
}
.hosts-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
    vertical-align: top;
}
.hosts-table tr:nth-child(even) {
    background-color: #f8f9fa;
}
.hosts-table tr:hover {
    background-color: #e9ecef;
}
.host-link {
    color: #007bff;
    text-decoration: none;
}
.host-link:hover {
    text-decoration: underline;
}
.interface-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    margin-right: 5px;
}
.interface-agent {
    background-color: #28a745;
    color: white;
}
.interface-snmp {
    background-color: #007bff;
    color: white;
}
.interface-ipmi {
    background-color: #fd7e14;
    color: white;
}
.interface-jmx {
    background-color: #6f42c1;
    color: white;
}
.no-data {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}
    </style>
</head>
<body>

<div class="cmdb-container">
    <h1><?= LanguageManager::t('Configuration Management Database') ?></h1>

    <div class="cmdb-header">
        <form method="get" action="zabbix.php">
            <input type="hidden" name="action" value="cmdb">
            <div class="search-form">
                <div class="form-field">
                    <label><?= LanguageManager::t('Search by hostname or IP') ?></label>
                    <input type="text" name="search" value="<?= htmlspecialchars($data['search']) ?>" placeholder="<?= LanguageManager::t('Search hosts...') ?>">
                </div>
                <div class="form-field">
                    <label><?= LanguageManager::t('Select host group') ?></label>
                    <select name="groupid">
                        <?= $groupOptions ?>
                    </select>
                </div>
                <button type="submit" class="btn-search"><?= LanguageManager::t('Search') ?></button>
                <button type="button" class="btn-clear" onclick="clearFilters()"><?= LanguageManager::t('Clear') ?></button>
            </div>
        </form>
    </div>

    <table class="hosts-table">
        <thead>
            <tr>
                <th><?= LanguageManager::t('Host Name') ?></th>
                <th><?= LanguageManager::t('IP Address') ?></th>
                <th><?= LanguageManager::t('Interface Type') ?></th>
                <th><?= LanguageManager::t('CPU Total') ?></th>
                <th><?= LanguageManager::t('Memory Total') ?></th>
                <th><?= LanguageManager::t('Host Group') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['hosts'])): ?>
                <tr>
                    <td colspan="6" class="no-data"><?= LanguageManager::t('No hosts found') ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($data['hosts'] as $host): ?>
                    <?php
                    // 获取主要IP地址
                    $mainIp = '';
                    $interfaceTypes = [];
                    foreach ($host['interfaces'] as $interface) {
                        if ($interface['main'] == 1) {
                            $mainIp = !empty($interface['ip']) ? $interface['ip'] : $interface['dns'];
                        }

                        // 收集接口类型
                        $typeClass = '';
                        $typeText = '';
                        switch ($interface['type']) {
                            case 1:
                                $typeClass = 'interface-agent';
                                $typeText = LanguageManager::t('Agent');
                                break;
                            case 2:
                                $typeClass = 'interface-snmp';
                                $typeText = LanguageManager::t('SNMP');
                                break;
                            case 3:
                                $typeClass = 'interface-ipmi';
                                $typeText = LanguageManager::t('IPMI');
                                break;
                            case 4:
                                $typeClass = 'interface-jmx';
                                $typeText = LanguageManager::t('JMX');
                                break;
                        }

                        if (!empty($typeText)) {
                            $interfaceTypes[] = '<span class="interface-type ' . $typeClass . '">' . $typeText . '</span>';
                        }
                    }

                    // 获取主机分组
                    $groupNames = array_column($host['groups'], 'name');
                    ?>
                    <tr>
                        <td><a href="zabbix.php?action=host.view&hostid=<?= $host['hostid'] ?>" class="host-link"><?= htmlspecialchars($host['name']) ?></a></td>
                        <td><?= htmlspecialchars($mainIp) ?></td>
                        <td><?= !empty($interfaceTypes) ? implode('', $interfaceTypes) : '-' ?></td>
                        <td><?= htmlspecialchars($host['cpu_total']) ?></td>
                        <td><?= htmlspecialchars($host['memory_total']) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $groupNames)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function clearFilters() {
    document.querySelector('input[name="search"]').value = "";
    document.querySelector('select[name="groupid"]').value = "0";
    document.querySelector('form').submit();
}
</script>

</body>
</html>
