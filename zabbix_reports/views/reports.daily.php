<?php

// 引入语言管理器
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixReports\Lib\LanguageManager;

// 添加自定义CSS
$page = new CHtmlPage();
$page->addItem((new CTag('style', true, '
.report-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}
.report-container h1 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.report-container h2 {
    color: #34495e;
    background-color: #ecf0f1;
    padding: 10px;
    margin: 20px 0 10px 0;
    border-left: 4px solid #3498db;
}
.report-container h3 {
    color: #555;
    margin: 15px 0 10px 0;
}
.report-container table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.report-container table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: bold;
    padding: 12px 8px;
    text-align: left;
    border: 1px solid #dee2e6;
}
.report-container table td {
    padding: 10px 8px;
    border: 1px solid #dee2e6;
    vertical-align: top;
}
.report-container table tr:nth-child(even) {
    background-color: #f8f9fa;
}
.report-container table tr:hover {
    background-color: #e9ecef;
}
.report-container button {
    margin: 10px 5px;
    padding: 10px 20px;
    font-size: 14px;
    border-radius: 4px;
}
.button-group {
    margin: 20px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    text-align: center;
}
.button-group button {
    margin: 0 10px;
    padding: 12px 25px;
    font-size: 16px;
    font-weight: bold;
    height: auto !important;
    min-height: 40px;
}
')));

// 创建告警信息表格
$alertTable = new CTable();
$alertTable->setHeader([LanguageManager::t('Host Name'), LanguageManager::t('Alert Name'), LanguageManager::t('Alert Time'), LanguageManager::t('Recovery Time')]);
if (!empty($data['alert_info'])) {
    $count = 0;
    foreach ($data['alert_info'] as $alert) {
        if ($count >= 10) break; // 显示前10条告警
        $alertTable->addRow([
            $alert['host'],
            $alert['alert'],
            $alert['time'],
            $alert['recovery_time'] ?: '-'
        ]);
        $count++;
    }
} else {
    $alertTable->addRow([LanguageManager::t('No alerts found'), '', '', '']);
}

// 创建主机群组信息表格
$groupTable = new CTable();
$groupTable->setHeader([LanguageManager::t('Host Group'), LanguageManager::t('Host Name'), LanguageManager::t('CPU Usage'), LanguageManager::t('CPU Total'), LanguageManager::t('Memory Usage'), LanguageManager::t('Memory Total')]);
if (!empty($data['hosts_by_group'])) {
    $count = 0;
    foreach ($data['hosts_by_group'] as $groupName => $hosts) {
        foreach ($hosts as $host) {
            if ($count >= 20) break; // 显示前20个主机
            $groupTable->addRow([
                $groupName,
                $host['name'],
                $host['cpu_usage'],
                $host['cpu_total'],
                $host['mem_usage'],
                $host['mem_total']
            ]);
            $count++;
        }
        if ($count >= 20) break;
    }
} else {
    $groupTable->addRow([LanguageManager::t('No host data available'), '', '', '', '', '']);
}

// 创建CPU信息表格（保留原有的Top 5显示）
$cpuTable = new CTable();
$cpuTable->setHeader([LanguageManager::t('Host Name'), LanguageManager::t('CPU Usage') . '(%)', LanguageManager::t('CPU Total')]);
$count = 0;
if (!empty($data['top_cpu_hosts'])) {
    foreach ($data['top_cpu_hosts'] as $host => $usage) {
        if ($count >= 5) break;
        $cpuUsage = number_format($usage, 2);
        $cpuTotal = isset($data['cpu_total'][$host]) ? $data['cpu_total'][$host] : 'N/A';
        $cpuTable->addRow([$host, $cpuUsage . '%', $cpuTotal]);
        $count++;
    }
} else {
    $cpuTable->addRow([LanguageManager::t('No data available'), '', '']);
}

// 创建内存信息表格（保留原有的Top 5显示）
$memTable = new CTable();
$memTable->setHeader([LanguageManager::t('Host Name'), LanguageManager::t('Memory Usage') . '(%)', LanguageManager::t('Memory Total') . '(GB)']);
$count = 0;
if (!empty($data['top_mem_hosts'])) {
    foreach ($data['top_mem_hosts'] as $host => $usage) {
        if ($count >= 5) break;
        $memUsage = number_format($usage, 2);
        $memTotal = isset($data['mem_total'][$host]) ? number_format($data['mem_total'][$host] / (1024*1024*1024), 2) : 'N/A';
        $memTable->addRow([$host, $memUsage . '%', $memTotal . ' GB']);
        $count++;
    }
} else {
    $memTable->addRow([LanguageManager::t('No data available'), '', '']);
}

$page
    ->setTitle(LanguageManager::t('Daily Report'))
    ->addItem(
        (new CDiv())
            ->addClass('report-container')
            ->addItem(
                (new CTag('h1', true, LanguageManager::t('Zabbix Daily Report') . ' - ' . $data['report_date']))
            )
            ->addItem(
                (new CDiv())
                    ->addClass('button-group')
                    ->addItem(
                        (new CButton('export_pdf', LanguageManager::t('Export PDF')))
                            ->onClick('javascript: window.open("?action=reports.daily.export&format=pdf", "_blank");')
                    )
                    ->addItem(
                        (new CButton('send_email', LanguageManager::t('Send Email')))
                            ->setAttribute('disabled', 'disabled')
                            ->setAttribute('title', LanguageManager::t('In Development'))
                    )
            )
            ->addItem(
                (new CForm())
                    ->addItem(
                        (new CTable())
                            ->addRow([LanguageManager::t('Problem Count'), $data['problem_count']])
                            ->addRow([LanguageManager::t('Resolved Count'), $data['resolved_count']])
                            ->addRow([LanguageManager::t('Top Problem Hosts'), implode(', ', array_keys($data['top_problem_hosts']))])
                    )
                    ->addItem(
                        (new CTag('h2', true, LanguageManager::t('Part 1: Alert Information')))
                    )
                    ->addItem($alertTable)
                    ->addItem(
                        (new CTag('h2', true, LanguageManager::t('Part 2: Host Group Information')))
                    )
                    ->addItem($groupTable)
                    ->addItem(
                        (new CTag('h3', true, LanguageManager::t('CPU Information (TOP 5)')))
                    )
                    ->addItem($cpuTable)
                    ->addItem(
                        (new CTag('h3', true, LanguageManager::t('Memory Information (TOP 5)')))
                    )
                    ->addItem($memTable)
            )
    )
    ->show();
