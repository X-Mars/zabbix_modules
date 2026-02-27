<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
require_once dirname(__DIR__) . '/lib/ReportViewHelper.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ViewRenderer;
use Modules\ZabbixReports\Lib\ReportViewHelper;

$pageTitle = $data['title'] ?? LanguageManager::t('Custom Report');
$styleTag = ReportViewHelper::getStyleTag();

// ===== 构建页面内容 =====
$content = (new CDiv())->addClass('rpt-container');

// 页面标题（自定义报表无导出按钮在标题栏，由日期表单区域控制）
$titleText = LanguageManager::t('Custom Report');
if (isset($data['period_text'])) {
    $titleText .= ' - ' . $data['period_text'];
}
$title = (new CTag('h1', true))
    ->addClass('rpt-title')
    ->addItem((new CSpan("\u{1F4CA}"))->addClass('rpt-title-icon'))
    ->addItem($titleText);
$content->addItem(
    (new CDiv())->addClass('rpt-header')->addItem($title)
);

// 日期选择表单
$dateForm = (new CForm())
    ->setMethod('get')
    ->setAction('zabbix.php')
    ->addClass('rpt-date-form')
    ->addItem(
        (new CInput('hidden', 'action', 'reports.custom'))
    )
    ->addItem(
        (new CDiv())
            ->addClass('rpt-form-group')
            ->addItem(
                (new CTag('label', true, LanguageManager::t('Start Date')))
                    ->addClass('rpt-form-label')
                    ->setAttribute('for', 'from_date')
            )
            ->addItem(
                (new CInput('date', 'from_date', $data['from_date']))
                    ->setId('from_date')
                    ->addClass('rpt-form-input')
                    ->setAttribute('required', 'required')
            )
    )
    ->addItem(
        (new CDiv())
            ->addClass('rpt-form-group')
            ->addItem(
                (new CTag('label', true, LanguageManager::t('End Date')))
                    ->addClass('rpt-form-label')
                    ->setAttribute('for', 'to_date')
            )
            ->addItem(
                (new CInput('date', 'to_date', $data['to_date']))
                    ->setId('to_date')
                    ->addClass('rpt-form-input')
                    ->setAttribute('required', 'required')
            )
    )
    ->addItem(
        (new CSubmit('generate', LanguageManager::t('Generate Report')))
            ->addClass('rpt-btn rpt-btn-primary')
    );
$content->addItem($dateForm);

// 错误提示
if (isset($data['error'])) {
    $content->addItem(
        (new CDiv())
            ->addClass('rpt-error')
            ->addItem("\u{26A0} " . $data['error'])
    );
}

// 有报表数据时显示
if (isset($data['report_generated']) && $data['report_generated']) {

    // 操作按钮
    $actions = (new CDiv())->addClass('rpt-actions');
    $actions->setAttribute('style', 'margin-bottom: 20px;');
    $exportUrl = '?action=reports.custom.export&format=pdf&from_date=' 
        . urlencode($data['from_date']) . '&to_date=' . urlencode($data['to_date']);
    $actions->addItem(
        (new CButton('export_pdf', ''))
            ->addClass('rpt-btn rpt-btn-primary')
            ->setAttribute('onclick', 'javascript: window.open("' . $exportUrl . '", "_blank");')
            ->addItem((new CSpan("\u{1F4E5}"))->addClass('rpt-btn-icon'))
            ->addItem(LanguageManager::t('Export PDF'))
    );
    $actions->addItem(
        (new CButton('send_email', ''))
            ->addClass('rpt-btn rpt-btn-outline')
            ->setAttribute('disabled', 'disabled')
            ->setAttribute('title', LanguageManager::t('In Development'))
            ->addItem((new CSpan("\u{2709}"))->addClass('rpt-btn-icon'))
            ->addItem(LanguageManager::t('Send Email'))
    );
    $content->addItem($actions);

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

} else if (!isset($data['error'])) {
    // 空状态引导
    $content->addItem(
        (new CDiv())
            ->addClass('rpt-empty')
            ->addItem((new CDiv("\u{1F4C5}"))->addClass('rpt-empty-icon'))
            ->addItem(
                (new CDiv(LanguageManager::t('Please select start and end dates to generate a custom report. Maximum date range is 90 days.')))
                    ->addClass('rpt-empty-text')
            )
    );
}

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $content);

// JS 增强
echo ReportViewHelper::getScriptTag();