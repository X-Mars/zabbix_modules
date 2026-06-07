<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixJumpserver\Lib\LanguageManager;
use Modules\ZabbixJumpserver\Lib\ConfigManager;
use Modules\ZabbixJumpserver\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? 'JumpServer';
$hosts = $data['hosts'] ?? [];
$hostGroups = $data['host_groups'] ?? [];
$selectedGroupid = (string) ($data['selected_groupid'] ?? '');
$selectedHostid = (string) ($data['selected_hostid'] ?? '');
$selectedSeverity = (string) ($data['selected_severity'] ?? '');
$searchTerm = (string) ($data['search'] ?? '');
$severityOptions = $data['severity_options'] ?? [];
$isConfigured = !empty($data['is_configured']);

// 严重度 => [显示名, CSS 类]
$severityMeta = [
    -1 => [LanguageManager::t('OK'), 'sev-ok'],
    0  => [LanguageManager::t('Not classified'), 'sev-0'],
    1  => [LanguageManager::t('Information'), 'sev-1'],
    2  => [LanguageManager::t('Warning'), 'sev-2'],
    3  => [LanguageManager::t('Average'), 'sev-3'],
    4  => [LanguageManager::t('High'), 'sev-4'],
    5  => [LanguageManager::t('Disaster'), 'sev-5'],
];

$pushedCount = 0;
foreach ($hosts as $h) {
    if (($h['asset_id'] ?? '') !== '') {
        $pushedCount++;
    }
}

$styleTag = new CTag('style', true, '
.jms-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}
.jms-toolbar {
    background-color: #f8f9fa;
    padding: 16px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
}
.jms-field {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}
.jms-field label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
    font-size: 13px;
}
.jms-field select,
.jms-field input[type="text"] {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
    height: 38px;
    box-sizing: border-box;
}
.jms-search-field { min-width: 240px; }
.jms-search-wrap { display: flex; gap: 8px; }
.jms-search-wrap input[type="text"] { flex: 1; }
.sev-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.sev-ok { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.sev-0 { background-color: #e9ecef; color: #495057; border: 1px solid #ced4da; }
.sev-1 { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.sev-2 { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.sev-3 { background-color: #ffe5d0; color: #8a4b1d; border: 1px solid #ffd5b5; }
.sev-4 { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.sev-5 { background-color: #d9534f; color: #fff; border: 1px solid #c9302c; }
.jms-toolbar-actions {
    display: flex;
    gap: 10px;
    margin-left: auto;
}
.jms-btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-align: center;
    height: 38px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}
.jms-btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}
.jms-btn-primary:hover {
    background-color: #0056b3;
    border-color: #004085;
}
.jms-btn-success {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}
.jms-btn-success:hover {
    background-color: #1e7e34;
    border-color: #1c7430;
}
.jms-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.jms-alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid #ffeeba;
    background-color: #fff3cd;
    color: #856404;
}
.jms-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.jms-stat-card {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.jms-stat-icon { font-size: 2rem; margin-right: 15px; }
.jms-stat-content { text-align: right; flex: 1; }
.jms-stat-number {
    font-size: 1.8rem;
    font-weight: 600;
    color: #495057;
    display: block;
}
.jms-stat-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.jms-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.jms-table thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 10px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dee2e6;
    white-space: nowrap;
}
.jms-table tbody td {
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: middle;
}
.jms-table tbody tr:hover { background-color: #f8f9fa; }
.jms-table tbody tr:last-child td { border-bottom: none; }
.host-link { color: #007bff; text-decoration: none; }
.host-link:hover { text-decoration: underline; }
.group-tag {
    background-color: #e7f3ff;
    color: #004085;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-right: 3px;
    margin-bottom: 2px;
    display: inline-block;
    border: 1px solid #b8daff;
}
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.status-pushed {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.status-pushed.status-repushable {
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
}
.status-pushed.status-repushable:hover {
    background-color: #cce5ff;
    color: #004085;
    border-color: #b8daff;
}
.status-repushable .label-hover {
    display: none;
}
.status-repushable:hover .label-default {
    display: none;
}
.status-repushable:hover .label-hover {
    display: inline;
}
.status-repushable[data-disabled="1"] {
    cursor: not-allowed;
    opacity: 0.75;
}
.status-repushable[data-disabled="1"]:hover {
    background-color: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}
.status-repushable[data-disabled="1"]:hover .label-default {
    display: inline;
}
.status-repushable[data-disabled="1"]:hover .label-hover {
    display: none;
}
.status-notpushed {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.jms-status-push-btn {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    color: #007bff;
    background-color: #fff;
    border: 1px solid #007bff;
    line-height: 1.6;
}
.jms-status-push-btn:hover {
    background-color: #007bff;
    color: #fff;
}
.jms-status-push-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.jms-row-btn {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid;
}
.jms-connect-btn {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}
.jms-connect-btn:hover { background-color: #1e7e34; color: #fff; }
.jms-push-btn {
    color: #007bff;
    background-color: #fff;
    border-color: #007bff;
}
.jms-push-btn:hover { background-color: #007bff; color: #fff; }
.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}
.jms-btn-info {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}
.jms-btn-info:hover {
    background-color: #117a8b;
    border-color: #10707f;
}
.jms-expand-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    cursor: pointer;
    color: #6c757d;
    user-select: none;
    transition: transform 0.15s;
    font-size: 12px;
}
.jms-expand-toggle.expanded {
    transform: rotate(90deg);
    color: #007bff;
}
.sev-badge + .sev-badge { margin-left: 4px; }
.jms-detail-row > td {
    background-color: #fbfcfd;
    padding: 0 !important;
}
.jms-detail-wrap {
    padding: 12px 16px 12px 46px;
}
.jms-detail-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 13px;
}
.jms-problem-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #e3e6ea;
    border-radius: 4px;
}
.jms-problem-table th {
    background-color: #f1f3f5;
    color: #495057;
    font-weight: 600;
    padding: 6px 10px;
    text-align: left;
    font-size: 12px;
    border-bottom: 1px solid #e3e6ea;
}
.jms-problem-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #f0f1f3;
    font-size: 12px;
}
.jms-problem-table tr:last-child td { border-bottom: none; }
.jms-no-problem {
    color: #28a745;
    font-size: 13px;
    padding: 4px 0;
}
');

$content = (new CDiv())->addClass('jms-container');

// 未配置提示
if (!$isConfigured) {
    $content->addItem(
        (new CDiv('⚠️ ' . LanguageManager::t('JumpServer is not configured. Please edit data/config.json.')))
            ->addClass('jms-alert')
    );
}

// ── 顶部工具栏：两个下拉框 + 两个推送按钮 ──
$form = (new CForm('get'))
    ->setAction('zabbix.php?action=jumpserver')
    ->addItem((new CInput('hidden', 'action', 'jumpserver')));

$toolbar = (new CDiv())->addClass('jms-toolbar');

// 主机分组下拉
$groupField = (new CDiv())->addClass('jms-field');
$groupField->addItem(new CLabel('📂 ' . LanguageManager::t('Select host group')));
$groupSelect = (new CTag('select', true))
    ->setAttribute('name', 'groupid')
    ->setAttribute('id', 'jms-group-select')
    ->setAttribute('onchange', 'jmsOnGroupChange(this)');
$optAllGroups = (new CTag('option', true, LanguageManager::t('All Groups')))->setAttribute('value', '0');
$groupSelect->addItem($optAllGroups);
foreach ($hostGroups as $group) {
    $opt = (new CTag('option', true, $group['name']))->setAttribute('value', (string) $group['groupid']);
    if ($selectedGroupid !== '' && $selectedGroupid === (string) $group['groupid']) {
        $opt->setAttribute('selected', 'selected');
    }
    $groupSelect->addItem($opt);
}
$groupField->addItem($groupSelect);
$toolbar->addItem($groupField);

// 主机下拉
$hostField = (new CDiv())->addClass('jms-field');
$hostField->addItem(new CLabel('🖥️ ' . LanguageManager::t('Select host')));
$hostSelect = (new CTag('select', true))
    ->setAttribute('name', 'hostid')
    ->setAttribute('id', 'jms-host-select')
    ->setAttribute('onchange', 'jmsOnHostChange(this)');
$optAllHosts = (new CTag('option', true, LanguageManager::t('All Hosts')))->setAttribute('value', '0');
$hostSelect->addItem($optAllHosts);
foreach ($hosts as $host) {
    $label = $host['name'] !== '' ? $host['name'] : $host['host'];
    $opt = (new CTag('option', true, $label))->setAttribute('value', (string) $host['hostid']);
    if ($selectedHostid !== '' && $selectedHostid === (string) $host['hostid']) {
        $opt->setAttribute('selected', 'selected');
    }
    $hostSelect->addItem($opt);
}
$hostField->addItem($hostSelect);
$toolbar->addItem($hostField);

// 告警状态下拉
$severityField = (new CDiv())->addClass('jms-field');
$severityField->addItem(new CLabel('🚨 ' . LanguageManager::t('Alarm Status')));
$severitySelect = (new CTag('select', true))
    ->setAttribute('name', 'severity')
    ->setAttribute('id', 'jms-severity-select')
    ->setAttribute('onchange', 'jmsOnFilterChange(this)');
$optAllSeverity = (new CTag('option', true, LanguageManager::t('All States')))->setAttribute('value', '');
$severitySelect->addItem($optAllSeverity);
foreach ($severityOptions as $sevValue => $sevLabel) {
    $opt = (new CTag('option', true, $sevLabel))->setAttribute('value', (string) $sevValue);
    if ($selectedSeverity !== '' && $selectedSeverity === (string) $sevValue) {
        $opt->setAttribute('selected', 'selected');
    }
    $severitySelect->addItem($opt);
}
$severityField->addItem($severitySelect);
$toolbar->addItem($severityField);

// 搜索框（IP / 主机名）
$searchField = (new CDiv())->addClass('jms-field jms-search-field');
$searchField->addItem(new CLabel('🔍 ' . LanguageManager::t('Search')));
$searchWrap = (new CDiv())->addClass('jms-search-wrap');
$searchInput = (new CTag('input', false))
    ->setAttribute('type', 'text')
    ->setAttribute('name', 'search')
    ->setAttribute('id', 'jms-search-input')
    ->setAttribute('placeholder', LanguageManager::t('Search by IP or host name'))
    ->setAttribute('value', $searchTerm);
$searchBtn = (new CTag('button', true, '🔍'))
    ->addClass('jms-btn jms-btn-primary')
    ->setAttribute('type', 'submit');
$searchWrap->addItem($searchInput);
$searchWrap->addItem($searchBtn);
$searchField->addItem($searchWrap);
$toolbar->addItem($searchField);

// 推送按钮组
$actions = (new CDiv())->addClass('jms-toolbar-actions');
$pushGroupsBtn = (new CTag('button', true, '📂 ' . LanguageManager::t('Push all host groups')))
    ->addClass('jms-btn jms-btn-primary')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'jms-push-groups');
$pushHostsBtn = (new CTag('button', true, '🚀 ' . LanguageManager::t('Push all hosts')))
    ->addClass('jms-btn jms-btn-success')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'jms-push-hosts');
$fetchIdsBtn = (new CTag('button', true, '🔄 ' . LanguageManager::t('Fetch JumpServer asset IDs')))
    ->addClass('jms-btn jms-btn-info')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'jms-fetch-ids');
if (!$isConfigured) {
    $pushGroupsBtn->setAttribute('disabled', 'disabled');
    $pushHostsBtn->setAttribute('disabled', 'disabled');
    $fetchIdsBtn->setAttribute('disabled', 'disabled');
}
$actions->addItem($pushGroupsBtn);
$actions->addItem($pushHostsBtn);
$actions->addItem($fetchIdsBtn);
$toolbar->addItem($actions);

$form->addItem($toolbar);
$content->addItem($form);

// ── 统计卡片 ──
$content->addItem(
    (new CDiv())
        ->addClass('jms-stats')
        ->addItem(
            (new CDiv())
                ->addClass('jms-stat-card')
                ->addItem((new CSpan('🖥️'))->addClass('jms-stat-icon'))
                ->addItem(
                    (new CDiv())
                        ->addClass('jms-stat-content')
                        ->addItem((new CDiv((string) count($hosts)))->addClass('jms-stat-number'))
                        ->addItem((new CDiv(LanguageManager::t('Total Hosts')))->addClass('jms-stat-label'))
                )
        )
        ->addItem(
            (new CDiv())
                ->addClass('jms-stat-card')
                ->addItem((new CSpan('🗂️'))->addClass('jms-stat-icon'))
                ->addItem(
                    (new CDiv())
                        ->addClass('jms-stat-content')
                        ->addItem((new CDiv((string) count($hostGroups)))->addClass('jms-stat-number'))
                        ->addItem((new CDiv(LanguageManager::t('Host Groups')))->addClass('jms-stat-label'))
                )
        )
        ->addItem(
            (new CDiv())
                ->addClass('jms-stat-card')
                ->addItem((new CSpan('✅'))->addClass('jms-stat-icon'))
                ->addItem(
                    (new CDiv())
                        ->addClass('jms-stat-content')
                        ->addItem((new CDiv((string) $pushedCount))->addClass('jms-stat-number'))
                        ->addItem((new CDiv(LanguageManager::t('Pushed Hosts')))->addClass('jms-stat-label'))
                )
        )
);

// ── 主机表格 ──
$table = (new CTable())->addClass('jms-table');
$table->setHeader([
    LanguageManager::t('#'),
    LanguageManager::t('Host Name'),
    LanguageManager::t('IP Address'),
    LanguageManager::t('Host Group'),
    LanguageManager::t('Alarm Status'),
    LanguageManager::t('JumpServer Status'),
    LanguageManager::t('Action'),
]);

if (empty($hosts)) {
    $table->addRow([
        (new CCol(LanguageManager::t('No hosts found')))
            ->addClass('no-data')
            ->setAttribute('colspan', 7)
    ]);
} else {
    $rowNum = 0;
    foreach ($hosts as $host) {
        $rowNum++;

        // 主机名（链接到 Zabbix 主机详情）
        $nameCol = new CCol(
            (new CLink(htmlspecialchars($host['name'] !== '' ? $host['name'] : $host['host']),
                'zabbix.php?action=host.view&hostid=' . $host['hostid']))->addClass('host-link')
        );

        // IP
        $ipCol = new CCol(htmlspecialchars($host['ip'] !== '' ? $host['ip'] : '-'));

        // 分组
        $groupCol = new CCol();
        if (!empty($host['group_names'])) {
            foreach ($host['group_names'] as $gn) {
                $groupCol->addItem((new CSpan(htmlspecialchars($gn)))->addClass('group-tag'));
                $groupCol->addItem(' ');
            }
        } else {
            $groupCol->addItem('-');
        }

        // 告警状态：按严重度展示各自数量；无告警显示“正常”
        $problemCounts = $host['problem_counts'] ?? [];
        $sevCol = new CCol();
        if (empty($problemCounts)) {
            [$okLabel, $okClass] = $severityMeta[-1];
            $sevCol->addItem((new CSpan($okLabel))->addClass('sev-badge ' . $okClass));
        } else {
            foreach ([5, 4, 3, 2, 1, 0] as $s) {
                $cnt = (int) ($problemCounts[$s] ?? 0);
                if ($cnt <= 0) {
                    continue;
                }
                [$lbl, $cls] = $severityMeta[$s];
                $sevCol->addItem(
                    (new CSpan($lbl . ' ' . $cnt))->addClass('sev-badge ' . $cls)
                );
            }
        }

        // 状态：未推送 → 推送按钮；已推送 → 已推送/悬停重新推送
        $statusCol = new CCol();
        $isPushed = ($host['asset_id'] ?? '') !== '';
        if ($isPushed) {
            $repushBadge = (new CSpan())
                ->addClass('status-badge status-pushed status-repushable')
                ->setAttribute('data-hostid', (string) $host['hostid'])
                ->setAttribute('title', LanguageManager::t('Re-push'));
            $repushBadge->addItem((new CSpan(LanguageManager::t('Pushed')))->addClass('label-default'));
            $repushBadge->addItem((new CSpan(LanguageManager::t('Re-push')))->addClass('label-hover'));
            if (!$isConfigured) {
                $repushBadge->setAttribute('data-disabled', '1');
            }
            $statusCol->addItem($repushBadge);
        } else {
            $pushBtn = (new CTag('button', true, '⬆️ ' . LanguageManager::t('Push')))
                ->addClass('jms-status-push-btn jms-push-btn')
                ->setAttribute('type', 'button')
                ->setAttribute('data-hostid', (string) $host['hostid']);
            if (!$isConfigured) {
                $pushBtn->setAttribute('disabled', 'disabled');
            }
            $statusCol->addItem($pushBtn);
        }

        // 操作列：已推送 → 连接
        $actionCol = new CCol();
        if ($isPushed) {
            $connectUrl = ConfigManager::buildConnectUrl($host['asset_id']);
            $actionCol->addItem(
                (new CLink('🔗 ' . LanguageManager::t('Connect'), $connectUrl))
                    ->addClass('jms-row-btn jms-connect-btn')
                    ->setAttribute('target', '_blank')
                    ->setAttribute('rel', 'noopener')
            );
        } else {
            $actionCol->addItem((new CSpan('-'))->setAttribute('style', 'color:#6c757d;'));
        }

        // 首列：展开切换 + 序号
        $hostidStr = (string) $host['hostid'];
        $toggle = (new CSpan('▶'))
            ->addClass('jms-expand-toggle')
            ->setAttribute('data-hostid', $hostidStr)
            ->setAttribute('title', LanguageManager::t('Show alarms'));
        $firstCol = (new CCol())
            ->setAttribute('style', 'white-space:nowrap;color:#6c757d;')
            ->addItem($toggle)
            ->addItem((new CSpan(' ' . $rowNum))->setAttribute('style', 'margin-left:4px;'));

        $mainRow = (new CRow([
            $firstCol,
            $nameCol,
            $ipCol,
            $groupCol,
            $sevCol,
            $statusCol,
            $actionCol,
        ]))->setAttribute('data-hostid', $hostidStr);
        $table->addRow($mainRow);

        // 展开明细行：展示该主机所有告警
        $detailWrap = (new CDiv())->addClass('jms-detail-wrap');
        $problems = $host['problems'] ?? [];
        if (empty($problems)) {
            $detailWrap->addItem(
                (new CDiv('✅ ' . LanguageManager::t('No active alarms')))->addClass('jms-no-problem')
            );
        } else {
            $detailWrap->addItem(
                (new CDiv(LanguageManager::t('Active alarms')))->addClass('jms-detail-title')
            );
            $pTable = (new CTag('table', true))->addClass('jms-problem-table');
            $thead = (new CTag('thead', true))->addItem(
                (new CTag('tr', true))
                    ->addItem(new CTag('th', true, LanguageManager::t('Severity')))
                    ->addItem(new CTag('th', true, LanguageManager::t('Problem')))
                    ->addItem(new CTag('th', true, LanguageManager::t('Time')))
            );
            $pTable->addItem($thead);
            $tbody = new CTag('tbody', true);
            foreach ($problems as $problem) {
                $ps = (int) ($problem['severity'] ?? 0);
                if (!isset($severityMeta[$ps])) {
                    $ps = 0;
                }
                [$pLabel, $pClass] = $severityMeta[$ps];
                $pTime = (int) ($problem['time'] ?? 0);
                $timeStr = $pTime > 0 ? date('Y-m-d H:i:s', $pTime) : '-';
                $tr = (new CTag('tr', true))
                    ->addItem(
                        (new CTag('td', true))->addItem(
                            (new CSpan($pLabel))->addClass('sev-badge ' . $pClass)
                        )
                    )
                    ->addItem(new CTag('td', true, htmlspecialchars((string) ($problem['name'] ?? ''))))
                    ->addItem(new CTag('td', true, $timeStr));
                $tbody->addItem($tr);
            }
            $pTable->addItem($tbody);
            $detailWrap->addItem($pTable);
        }

        $detailRow = (new CRow([
            (new CCol($detailWrap))->setAttribute('colspan', 7)
        ]))
            ->addClass('jms-detail-row')
            ->setAttribute('data-detail-hostid', $hostidStr)
            ->setAttribute('style', 'display:none;');
        $table->addRow($detailRow);
    }
}

$content->addItem($table);

// ── JavaScript ──
$jsLabels = json_encode([
    'pushing'       => LanguageManager::t('Pushing...'),
    'confirmGroups' => LanguageManager::t('Confirm push all host groups to JumpServer?'),
    'confirmHosts'  => LanguageManager::t('Confirm push all hosts to JumpServer?'),
    'pushCompleted' => LanguageManager::t('Push completed'),
    'pushFailed'    => LanguageManager::t('Push failed'),
    'created'       => LanguageManager::t('Created'),
    'updated'       => LanguageManager::t('Updated'),
    'failed'        => LanguageManager::t('Failed'),
    'accountsLinked' => LanguageManager::t('Accounts linked'),
    'confirmFetch'  => LanguageManager::t('Fetch all assets from JumpServer and match by IP to update host tags?'),
    'fetching'      => LanguageManager::t('Fetching...'),
    'fetchCompleted' => LanguageManager::t('Fetch completed'),
    'fetchFailed'   => LanguageManager::t('Fetch failed'),
    'matched'       => LanguageManager::t('Matched'),
    'updated'       => LanguageManager::t('Updated'),
    'skipped'       => LanguageManager::t('Skipped'),
], JSON_UNESCAPED_UNICODE);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var labels = ' . $jsLabels . ';

    function jmsBuildSummary(summary) {
        if (!summary) { return ""; }
        var parts = [];
        if (summary.type === "groups") {
            parts.push(labels.created + ": " + (summary.created || 0));
            parts.push(labels.failed + ": " + (summary.failed || 0));
        } else {
            parts.push(labels.created + ": " + (summary.created || 0));
            parts.push(labels.updated + ": " + (summary.updated || 0));
            parts.push(labels.failed + ": " + (summary.failed || 0));
            if (summary.accounts_linked || summary.accounts_failed) {
                var acct = labels.accountsLinked + ": " + (summary.accounts_linked || 0);
                if (summary.accounts_failed) {
                    acct += " / " + labels.failed + ": " + summary.accounts_failed;
                }
                parts.push(acct);
            }
        }
        return " (" + parts.join(", ") + ")";
    }

    function jmsPush(mode, hostids, btn) {
        var originalText = null;
        if (btn) {
            originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = labels.pushing;
        }

        var params = new URLSearchParams();
        params.append("mode", mode);
        if (hostids) {
            params.append("hostids", hostids);
        }

        fetch("zabbix.php?action=jumpserver.push", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: params.toString()
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data && data.ok) {
                    alert((data.message || labels.pushCompleted) + jmsBuildSummary(data.summary));
                    window.location.reload();
                } else {
                    alert((data && data.message) ? data.message : labels.pushFailed);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                }
            })
            .catch(function(err) {
                alert(labels.pushFailed + ": " + String(err));
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
    }

    window.jmsOnGroupChange = function(select) {
        var form = select.closest("form");
        // 切换分组时重置主机选择
        var hostSelect = document.getElementById("jms-host-select");
        if (hostSelect) { hostSelect.value = "0"; }
        if (form) { form.submit(); }
    };

    window.jmsOnHostChange = function(select) {
        var form = select.closest("form");
        if (form) { form.submit(); }
    };

    window.jmsOnFilterChange = function(select) {
        var form = select.closest("form");
        if (form) { form.submit(); }
    };

    function jmsFetchIds(btn) {
        var originalText = btn ? btn.textContent : null;
        if (btn) {
            btn.disabled = true;
            btn.textContent = labels.fetching;
        }

        fetch("zabbix.php?action=jumpserver.fetchids", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: ""
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data && data.ok) {
                    var s = data.summary || {};
                    var parts = [
                        labels.matched + ": " + (s.matched || 0),
                        labels.updated + ": " + (s.updated || 0),
                        labels.skipped + ": " + (s.skipped || 0),
                        labels.failed + ": " + (s.failed || 0)
                    ];
                    alert((data.message || labels.fetchCompleted) + " (" + parts.join(", ") + ")");
                    window.location.reload();
                } else {
                    alert((data && data.message) ? data.message : labels.fetchFailed);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                }
            })
            .catch(function(err) {
                alert(labels.fetchFailed + ": " + String(err));
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
    }

    document.addEventListener("DOMContentLoaded", function() {
        var pushGroups = document.getElementById("jms-push-groups");
        if (pushGroups) {
            pushGroups.addEventListener("click", function() {
                if (confirm(labels.confirmGroups)) {
                    jmsPush("all_groups", null, pushGroups);
                }
            });
        }

        var pushHosts = document.getElementById("jms-push-hosts");
        if (pushHosts) {
            pushHosts.addEventListener("click", function() {
                if (confirm(labels.confirmHosts)) {
                    jmsPush("all_hosts", null, pushHosts);
                }
            });
        }

        document.querySelectorAll(".jms-push-btn").forEach(function(btn) {
            btn.addEventListener("click", function() {
                var hostid = btn.getAttribute("data-hostid");
                if (hostid) {
                    jmsPush("selected", hostid, btn);
                }
            });
        });

        document.querySelectorAll(".status-repushable").forEach(function(badge) {
            badge.addEventListener("click", function() {
                if (badge.getAttribute("data-disabled") === "1") {
                    return;
                }
                var hostid = badge.getAttribute("data-hostid");
                if (hostid) {
                    jmsPush("selected", hostid, null);
                }
            });
        });

        var fetchBtn = document.getElementById("jms-fetch-ids");
        if (fetchBtn) {
            fetchBtn.addEventListener("click", function() {
                if (confirm(labels.confirmFetch)) {
                    jmsFetchIds(fetchBtn);
                }
            });
        }

        document.querySelectorAll(".jms-expand-toggle").forEach(function(toggle) {
            toggle.addEventListener("click", function() {
                var hostid = toggle.getAttribute("data-hostid");
                if (!hostid) { return; }
                var detail = document.querySelector(".jms-detail-row[data-detail-hostid=\"" + hostid + "\"]");
                if (!detail) { return; }
                var isHidden = detail.style.display === "none" || detail.style.display === "";
                detail.style.display = isHidden ? "table-row" : "none";
                toggle.classList.toggle("expanded", isHidden);
            });
        });
    });
})();
</script>'));

ViewRenderer::render($pageTitle, $styleTag, $content);
