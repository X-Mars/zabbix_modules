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
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: 0;
    padding: 20px;
    min-height: 100vh;
}

.cmdb-container {
    max-width: 1600px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.cmdb-header {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    padding: 30px;
    color: white;
}

.cmdb-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.cmdb-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0 0 30px 0;
}

.search-container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 20px;
    align-items: end;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

.form-field {
    display: flex;
    flex-direction: column;
}

.form-field label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #2d3748;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-field input,
.form-field select {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f7fafc;
}

.form-field input:focus,
.form-field select:focus {
    outline: none;
    border-color: #4f46e5;
    background: white;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    transform: translateY(-1px);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: 120px;
}

.btn-search {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.btn-clear {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    color: #8b4513;
    box-shadow: 0 4px 15px rgba(252, 182, 159, 0.4);
}

.btn-clear:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(252, 182, 159, 0.6);
}

.table-container {
    padding: 30px;
    background: white;
}

.hosts-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.hosts-table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    padding: 18px 15px;
    text-align: left;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: none;
}

.hosts-table thead th:first-child {
    border-top-left-radius: 15px;
}

.hosts-table thead th:last-child {
    border-top-right-radius: 15px;
}

.hosts-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    vertical-align: middle;
    transition: all 0.3s ease;
}

.hosts-table tbody tr {
    transition: all 0.3s ease;
}

.hosts-table tbody tr:hover {
    background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
    transform: scale(1.01);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.hosts-table tbody tr:last-child td {
    border-bottom: none;
}

.hosts-table tbody tr:last-child td:first-child {
    border-bottom-left-radius: 15px;
}

.hosts-table tbody tr:last-child td:last-child {
    border-bottom-right-radius: 15px;
}

.host-link {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

.host-link:hover {
    color: #3730a3;
    text-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
}

.host-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -2px;
    left: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: width 0.3s ease;
}

.host-link:hover::after {
    width: 100%;
}

.interface-type {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 6px;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.interface-agent {
    background: linear-gradient(135deg, #81c784 0%, #4caf50 100%);
    color: white;
}

.interface-snmp {
    background: linear-gradient(135deg, #64b5f6 0%, #2196f3 100%);
    color: white;
}

.interface-ipmi {
    background: linear-gradient(135deg, #ffb74d 0%, #ff9800 100%);
    color: white;
}

.interface-jmx {
    background: linear-gradient(135deg, #ba68c8 0%, #9c27b0 100%);
    color: white;
}

.status-enabled {
    color: #22c55e;
    font-weight: 600;
}

.status-disabled {
    color: #ef4444;
    font-weight: 600;
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
    font-style: italic;
    font-size: 1.1rem;
    background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #4f46e5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fade-in {
    animation: fadeIn 0.6s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
    </style>
</head>
<body>

<div class="cmdb-container fade-in">
    <div class="cmdb-header">
        <h1 class="cmdb-title"><?= LanguageManager::t('CMDB') ?></h1>
        <p class="cmdb-subtitle"><?= LanguageManager::t('Configuration Management Database') ?></p>
        
        <div class="search-container">
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
                    <button type="submit" class="btn btn-search"><?= LanguageManager::t('Search') ?></button>
                    <button type="button" class="btn btn-clear" onclick="clearFilters()"><?= LanguageManager::t('Clear') ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($data['hosts'])): ?>
    <div class="table-container">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?= count($data['hosts']) ?></div>
                <div class="stat-label">Total Hosts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($data['host_groups']) ?></div>
                <div class="stat-label">Host Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($data['hosts'], function($h) { return $h['status'] == 0; })) ?></div>
                <div class="stat-label">Active Hosts</div>
            </div>
        </div>
    <?php endif; ?>

        <table class="hosts-table">
            <thead>
                <tr>
                    <th><?= LanguageManager::t('Host Name') ?></th>
                    <th><?= LanguageManager::t('IP Address') ?></th>
                    <th><?= LanguageManager::t('Interface Type') ?></th>
                    <th><?= LanguageManager::t('CPU Total') ?></th>
                    <th><?= LanguageManager::t('Memory Total') ?></th>
                    <th><?= LanguageManager::t('Kernel Version') ?></th>
                    <th><?= LanguageManager::t('Host Group') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data['hosts'])): ?>
                <tr>
                    <td colspan="7" class="no-data">
                        <div>
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <br><br>
                            <?= LanguageManager::t('No hosts found') ?>
                        </div>
                    </td>
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
                    
                    // 简化内核版本显示
                    $kernelVersion = $host['kernel_version'];
                    if ($kernelVersion !== '-' && strlen($kernelVersion) > 30) {
                        $kernelVersion = substr($kernelVersion, 0, 30) . '...';
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="zabbix.php?action=host.view&hostid=<?= $host['hostid'] ?>" class="host-link">
                                <?= htmlspecialchars($host['name']) ?>
                            </a>
                            <br>
                            <small style="color: #64748b;">
                                <span class="<?= $host['status'] == 0 ? 'status-enabled' : 'status-disabled' ?>">
                                    <?= $host['status'] == 0 ? '● Active' : '● Disabled' ?>
                                </span>
                            </small>
                        </td>
                        <td>
                            <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 12px;">
                                <?= htmlspecialchars($mainIp) ?>
                            </code>
                        </td>
                        <td><?= !empty($interfaceTypes) ? implode('', $interfaceTypes) : '<span style="color: #64748b;">-</span>' ?></td>
                        <td>
                            <?php if ($host['cpu_total'] !== '-'): ?>
                                <span style="font-weight: 600; color: #4f46e5;"><?= htmlspecialchars($host['cpu_total']) ?></span> cores
                            <?php else: ?>
                                <span style="color: #64748b;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($host['memory_total'] !== '-'): ?>
                                <span style="font-weight: 600; color: #059669;"><?= htmlspecialchars($host['memory_total']) ?></span>
                            <?php else: ?>
                                <span style="color: #64748b;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($kernelVersion !== '-'): ?>
                                <code style="background: #fef3c7; padding: 4px 8px; border-radius: 6px; font-size: 11px; color: #92400e;" title="<?= htmlspecialchars($host['kernel_version']) ?>">
                                    <?= htmlspecialchars($kernelVersion) ?>
                                </code>
                            <?php else: ?>
                                <span style="color: #64748b;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach ($groupNames as $index => $groupName): ?>
                                <span style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-right: 4px; margin-bottom: 2px; display: inline-block;">
                                    <?= htmlspecialchars($groupName) ?>
                                </span>
                                <?php if ($index < count($groupNames) - 1): ?><br><?php endif; ?>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (!empty($data['hosts'])): ?>
    </div>
    <?php endif; ?>
</div>

<script>
function clearFilters() {
    document.querySelector('input[name="search"]').value = "";
    document.querySelector('select[name="groupid"]').value = "0";
    document.querySelector('form').submit();
}

// 添加加载动画
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const searchBtn = document.querySelector('.btn-search');
    
    form.addEventListener('submit', function() {
        searchBtn.innerHTML = '<span class="loading-spinner"></span> Loading...';
        searchBtn.disabled = true;
    });
});
</script>

</body>
</html>
