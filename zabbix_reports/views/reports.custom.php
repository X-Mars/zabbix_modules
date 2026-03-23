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

// 页面标题 + 操作按钮（使用 buildHeader 与日报一致）
$titleText = LanguageManager::t('Custom Report');
if (isset($data['period_text'])) {
    $titleText .= ' - ' . $data['period_text'];
}
if (!empty($data['filter_group_name'])) {
    $titleText .= ' [' . $data['filter_group_name'] . ']';
}

// 构建导出参数
$exportParams = ['from_date' => $data['from_date'], 'to_date' => $data['to_date'], 'groupid' => $data['filter_groupid'] ?? ''];
$content->addItem(
    ReportViewHelper::buildHeader(
        $titleText,
        "\u{1F4CA}",
        'reports.custom.export',
        $exportParams
    )
);

// 日期选择表单 - 使用 div + JS 避免 CHtmlPage 嵌套 form 问题
$dateForm = (new CDiv())
    ->addClass('rpt-date-form')
    ->setAttribute('id', 'custom-report-form');

$dateForm->addItem(
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
);
$dateForm->addItem(
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
);

// 主机群组选择
$allGroups = $data['all_groups'] ?? [];
$selectedGroupId = $data['filter_groupid'] ?? '';

$groupDiv = (new CDiv())->addClass('rpt-form-group');
$groupDiv->addItem(
    (new CTag('label', true, LanguageManager::t('Host Group')))->addClass('rpt-form-label')
);
$groupSelect = (new CTag('select', true))
    ->setAttribute('id', 'custom_groupid')
    ->addClass('rpt-form-select');
$groupSelect->addItem(
    (new CTag('option', true, LanguageManager::t('All Groups')))->setAttribute('value', '')
);
foreach ($allGroups as $group) {
    $opt = (new CTag('option', true, $group['name']))
        ->setAttribute('value', $group['groupid']);
    if ((string)$group['groupid'] === (string)$selectedGroupId) {
        $opt->setAttribute('selected', 'selected');
    }
    $groupSelect->addItem($opt);
}
$groupDiv->addItem($groupSelect);
$dateForm->addItem($groupDiv);

$generateBtn = (new CButton('generate', LanguageManager::t('Generate Report')))
    ->addClass('rpt-btn rpt-btn-primary')
    ->setAttribute('onclick', 'var f=document.getElementById("from_date").value,t=document.getElementById("to_date").value,g=document.getElementById("custom_groupid").value;if(!f||!t){alert("Please select dates");return;}var u="zabbix.php?action=reports.custom&generate=1&from_date="+encodeURIComponent(f)+"&to_date="+encodeURIComponent(t);if(g)u+="&groupid="+encodeURIComponent(g);window.location.href=u;');
$dateForm->addItem($generateBtn);
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