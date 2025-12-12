<?php

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ViewRenderer;

// 从控制器获取标题
$pageTitle = $data['title'] ?? LanguageManager::t('Custom Report');

// 创建告警信息表格
$buildAlertTable = static function(array $alerts) {
    $table = new CTable();
    $table->setHeader([
        LanguageManager::t('Host Name'), 
        LanguageManager::t('Alert Name'), 
        LanguageManager::t('Alert Time'), 
        LanguageManager::t('Recovery Time')
    ]);
    
    if (!empty($alerts)) {
        $count = 0;
        foreach ($alerts as $alert) {
            if ($count >= 10) break; // 显示前10条告警
            $table->addRow([
                $alert['host'],
                $alert['alert'],
                $alert['time'],
                $alert['recovery_time'] ?: '-'
            ]);
            $count++;
        }
    } else {
        $table->addRow([LanguageManager::t('No alerts found'), '', '', '']);
    }
    return $table;
};

// 创建主机群组信息表格
$buildGroupTable = static function(array $hostsByGroup) {
    $table = new CTable();
    $table->setHeader([
        LanguageManager::t('Host Group'), 
        LanguageManager::t('Host Name'), 
        LanguageManager::t('CPU Usage'), 
        LanguageManager::t('CPU Total'), 
        LanguageManager::t('Memory Usage'), 
        LanguageManager::t('Memory Total')
    ]);
    
    if (!empty($hostsByGroup)) {
        $count = 0;
        foreach ($hostsByGroup as $groupName => $hosts) {
            foreach ($hosts as $host) {
                if ($count >= 20) break; // 显示前20个主机
                $table->addRow([
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
        $table->addRow([LanguageManager::t('No host data available'), '', '', '', '', '']);
    }
    return $table;
};

// 创建CPU信息表格（保留原有的Top 5显示）
$buildCpuTable = static function(array $topCpuHosts, array $cpuTotal) {
    $table = new CTable();
    $table->setHeader([
        LanguageManager::t('Host Name'), 
        LanguageManager::t('CPU Usage') . '(%)', 
        LanguageManager::t('CPU Total')
    ]);
    
    $count = 0;
    if (!empty($topCpuHosts)) {
        foreach ($topCpuHosts as $host => $usage) {
            if ($count >= 5) break;
            $cpuUsage = number_format($usage, 2);
            $cpuTotalValue = isset($cpuTotal[$host]) ? $cpuTotal[$host] : 'N/A';
            $table->addRow([$host, $cpuUsage . '%', $cpuTotalValue]);
            $count++;
        }
    } else {
        $table->addRow([LanguageManager::t('No data available'), '', '']);
    }
    return $table;
};

// 创建内存信息表格（保留原有的Top 5显示）
$buildMemTable = static function(array $topMemHosts, array $memTotal) {
    $table = new CTable();
    $table->setHeader([
        LanguageManager::t('Host Name'), 
        LanguageManager::t('Memory Usage') . '(%)', 
        LanguageManager::t('Memory Total') . '(GB)'
    ]);
    
    $count = 0;
    if (!empty($topMemHosts)) {
        foreach ($topMemHosts as $host => $usage) {
            if ($count >= 5) break;
            $memUsage = number_format($usage, 2);
            $memTotalValue = isset($memTotal[$host]) ? number_format($memTotal[$host] / (1024*1024*1024), 2) : 'N/A';
            $table->addRow([$host, $memUsage . '%', $memTotalValue . ' GB']);
            $count++;
        }
    } else {
        $table->addRow([LanguageManager::t('No data available'), '', '']);
    }
    return $table;
};

// 添加自定义CSS
$styleTag = (new CTag('style', true, '
.report-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}
.date-form-container {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: center;
}
.date-form {
    display: inline-flex;
    gap: 20px;
    align-items: end;
    flex-wrap: wrap;
    justify-content: center;
}
.form-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}
.form-group label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}
.form-group input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}
.btn-primary {
    padding: 12px 25px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    min-height: 40px;
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.btn-primary:hover {
    background: #0056b3;
}
.info-box {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
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
    border-radius: 4px;
}
.report-container button {
    margin: 10px 5px;
    padding: 10px 20px;
    font-size: 14px;
    border-radius: 4px;
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
'));

// 创建页面内容
$content = (new CDiv())
            ->addClass('report-container')
            ->addItem([
                // 页面标题
                (new CTag('h1', true))
                    ->addItem('📊 ' . LanguageManager::t('Custom Report') . 
                        (isset($data['period_text']) ? ' - ' . $data['period_text'] : '')),

                // 日期选择表单
                (new CDiv())
                    ->addClass('date-form-container')
                    ->addItem([
                        (new CForm())
                            ->setMethod('get')
                            ->setAction('zabbix.php')
                            ->addItem([
                                (new CInput('hidden', 'action', 'reports.custom')),
                                
                                (new CDiv())
                                    ->addClass('date-form')
                                    ->addItem([
                                        (new CDiv())
                                            ->addClass('form-group')
                                            ->addItem([
                                                (new CLabel(LanguageManager::t('Start Date'), 'from_date')),
                                                (new CInput('date', 'from_date', $data['from_date']))
                                                    ->setId('from_date')
                                                    ->setAttribute('required', 'required')
                                            ]),
                                        
                                        (new CDiv())
                                            ->addClass('form-group')
                                            ->addItem([
                                                (new CLabel(LanguageManager::t('End Date'), 'to_date')),
                                                (new CInput('date', 'to_date', $data['to_date']))
                                                    ->setId('to_date')
                                                    ->setAttribute('required', 'required')
                                            ]),
                                        
                                        (new CDiv())
                                            ->addItem(
                                                (new CSubmit('generate', LanguageManager::t('Generate Report')))
                                                    ->addClass('btn-primary')
                                                    ->setAttribute('style', 'padding: 12px 25px; font-size: 16px; font-weight: bold; min-height: 40px; margin-top: 10px;')
                                            )
                                    ])
                            ])
                    ]),

                // 显示错误信息
                isset($data['error']) ? 
                    (new CDiv())
                        ->addClass('info-box')
                        ->addItem((new CTag('p', true))
                            ->addItem('错误: ' . $data['error'])
                            ->setAttribute('style', 'color: red;')
                        ) : null,

                // 显示报表内容或提示信息
                (isset($data['report_generated']) && $data['report_generated']) ? [
                // 导出按钮 - 使用与日报相同的样式
                (new CDiv())
                    ->addClass('button-group')
                    ->addItem([
                        (new CButton('export_pdf', LanguageManager::t('Export PDF')))
                            ->onClick('javascript: window.open("?action=reports.custom.export&format=pdf&from_date=' . urlencode($data['from_date']) . '&to_date=' . urlencode($data['to_date']) . '", "_blank");'),
                        (new CButton('send_email', LanguageManager::t('Send Email')))
                            ->setAttribute('disabled', 'disabled')
                            ->setAttribute('title', LanguageManager::t('In Development'))
                    ]),                    // 统计摘要
                    (new CForm())
                        ->addItem(
                            (new CTable())
                                ->addRow([LanguageManager::t('Report Period'), $data['period_text'] ?? ''])
                                ->addRow([LanguageManager::t('Problem Count'), $data['problem_count'] ?? 0])
                                ->addRow([LanguageManager::t('Resolved Count'), $data['resolved_count'] ?? 0])
                                ->addRow([LanguageManager::t('Top Problem Hosts'), implode(', ', array_keys($data['top_problem_hosts'] ?? []))])
                        )
                        ->addItem(
                            (new CTag('h2', true, LanguageManager::t('Part 1: Alert Information')))
                        )
                        ->addItem($buildAlertTable($data['alert_info'] ?? []))
                        ->addItem(
                            (new CTag('h2', true, LanguageManager::t('Part 2: Host Group Information')))
                        )
                        ->addItem($buildGroupTable($data['hosts_by_group'] ?? []))
                        ->addItem(
                            (new CTag('h3', true, LanguageManager::t('CPU Information (TOP 5)')))
                        )
                        ->addItem($buildCpuTable($data['top_cpu_hosts'] ?? [], $data['cpu_total'] ?? []))
                        ->addItem(
                            (new CTag('h3', true, LanguageManager::t('Memory Information (TOP 5)')))
                        )
                        ->addItem($buildMemTable($data['top_mem_hosts'] ?? [], $data['mem_total'] ?? []))

                ] : (new CDiv())
                    ->addClass('info-box')
                    ->addItem(
                        (new CTag('p', true))
                            ->addItem(LanguageManager::t('Please select start and end dates to generate a custom report. Maximum date range is 90 days.'))
                    )
            ]);

// 使用兼容渲染器显示页面（模块视图需要直接输出）
ViewRenderer::render($pageTitle, $styleTag, $content);