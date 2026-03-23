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
$exportParams = ['groupid' => $data['filter_groupid'] ?? ''];
$filterYear = $data['filter_year'] ?? (int)date('Y');
$filterWeek = $data['filter_week'] ?? (int)date('W');
$exportParams['year'] = $filterYear;
$exportParams['week'] = $filterWeek;

$titleText = LanguageManager::t('Zabbix Weekly Report') . ' - ' . $data['report_period'];
if (!empty($data['filter_group_name'])) {
    $titleText .= ' [' . $data['filter_group_name'] . ']';
}

// 构建年/周下拉选项
$currentYear = (int)date('Y');
$yearOptions = [
    ['value' => (string)$currentYear, 'label' => (string)$currentYear],
    ['value' => (string)($currentYear - 1), 'label' => (string)($currentYear - 1)]
];
$maxWeek = (int)date('W', mktime(0, 0, 0, 12, 28, $filterYear));
$isChinese = LanguageManager::isChinese();
$weekOptions = [];
for ($w = 1; $w <= $maxWeek; $w++) {
    $label = $isChinese ? '第' . $w . '周' : 'W' . sprintf('%02d', $w);
    $weekOptions[] = ['value' => (string)$w, 'label' => $label];
}

$content->addItem(
    ReportViewHelper::buildHeader(
        $titleText,
        "\u{1F4C6}",
        'reports.weekly.export',
        $exportParams,
        [
            'action_name' => 'reports.weekly',
            'all_groups' => $data['all_groups'] ?? [],
            'filter_groupid' => $data['filter_groupid'] ?? '',
            'date_selects' => [
                ['name' => 'year', 'selected' => $filterYear, 'options' => $yearOptions],
                ['name' => 'week', 'selected' => $filterWeek, 'options' => $weekOptions]
            ]
        ]
    )
);

// 统计摘要卡片
$content->addItem(ReportViewHelper::buildStatsGrid($data));

// Top CPU / Memory 双列
$content->addItem(ReportViewHelper::buildTopResourceSection($data));

// 告警级别雷达 + 主机组问题分布双列图表
$content->addItem(ReportViewHelper::buildChartsRow($data['alert_info'] ?? [], $data['hosts_by_group'] ?? []));

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
