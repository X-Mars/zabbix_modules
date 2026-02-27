<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
require_once dirname(__DIR__) . '/lib/ReportViewHelper.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ViewRenderer;
use Modules\ZabbixReports\Lib\ReportViewHelper;

$pageTitle = $data['title'] ?? LanguageManager::t('Weekly Report');
$styleTag = ReportViewHelper::getStyleTag();

// ===== 构建页面内容 =====
$content = (new CDiv())->addClass('rpt-container');

// 标题 + 操作按钮
$content->addItem(
    ReportViewHelper::buildHeader(
        LanguageManager::t('Zabbix Weekly Report') . ' - ' . $data['report_period'],
        "\u{1F4C6}",
        'reports.weekly.export'
    )
);

// 统计摘要卡片
$content->addItem(ReportViewHelper::buildStatsGrid($data));

// Top CPU / Memory 双列
$content->addItem(ReportViewHelper::buildTopResourceSection($data));

// 问题主机标签
$content->addItem(ReportViewHelper::buildTopProblemHosts($data['top_problem_hosts'] ?? []));

// 告警信息
$content->addItem(ReportViewHelper::buildAlertSection($data['alert_info'] ?? []));

// 主机群组信息
$content->addItem(ReportViewHelper::buildHostGroupSection($data['hosts_by_group'] ?? []));

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $content);

// JS 增强
echo ReportViewHelper::getScriptTag();
