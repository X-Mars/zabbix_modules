<?php
/**
 * CMDB 主机分组视图
 * 
 * 功能特性：
 * - 分页组件：页码切换、每页数量切换、页面跳转
 * - 搜索功能
 * - 统计信息
 * - 兼容 Zabbix 6.0、7.0、7.4
 */

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

/**
 * 构建分页URL
 */
function buildPageUrl($data, $page = null, $perPage = null) {
    $params = [
        'action' => 'cmdb.groups',
        'page' => $page ?? $data['page'],
        'per_page' => $perPage ?? $data['per_page'],
    ];
    
    if (!empty($data['search'])) {
        $params['search'] = $data['search'];
    }
    if (!empty($data['sort'])) {
        $params['sort'] = $data['sort'];
    }
    if (!empty($data['sortorder'])) {
        $params['sortorder'] = $data['sortorder'];
    }
    
    return 'zabbix.php?' . http_build_query($params, '', '&amp;');
}

/**
 * 创建排序链接
 */
function createSortLink($title, $field, $data) {
    $currentSort = isset($data['sort']) ? $data['sort'] : 'host_count';
    $currentOrder = isset($data['sortorder']) ? $data['sortorder'] : 'DESC';

    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';

    $icon = '';
    if ($currentSort === $field) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }

    $params = [
        'action' => 'cmdb.groups',
        'sort' => $field,
        'sortorder' => $newOrder,
        'page' => 1,
        'per_page' => $data['per_page'] ?? 25,
    ];
    
    if (!empty($data['search'])) {
        $params['search'] = $data['search'];
    }

    return (new CLink($title . $icon, 'zabbix.php?' . http_build_query($params, '', '&amp;')))
        ->addClass('sort-link');
}

/**
 * 创建分页组件
 */
function createPagination($data) {
    $page = (int)$data['page'];
    $perPage = (int)$data['per_page'];
    $totalGroups = (int)$data['total_groups'];
    $totalPages = (int)$data['total_pages'];
    $allowedPerPage = $data['allowed_per_page'] ?? [10, 25, 50, 100];
    
    $start = ($page - 1) * $perPage + 1;
    $end = min($page * $perPage, $totalGroups);
    
    $container = (new CDiv())->addClass('pagination-container');
    
    // 左侧：显示信息
    $infoText = sprintf(
        LanguageManager::t('Showing %d-%d of %d groups'),
        $totalGroups > 0 ? $start : 0,
        $end,
        $totalGroups
    );
    $infoDiv = (new CDiv($infoText))->addClass('pagination-info');
    
    // 中间：页码导航
    $navDiv = (new CDiv())->addClass('pagination-nav');
    
    // 首页和上一页
    if ($page > 1) {
        $navDiv->addItem((new CLink('«', buildPageUrl($data, 1)))->addClass('page-link')->setAttribute('title', LanguageManager::t('First Page')));
        $navDiv->addItem((new CLink('‹', buildPageUrl($data, $page - 1)))->addClass('page-link')->setAttribute('title', LanguageManager::t('Previous Page')));
    } else {
        $navDiv->addItem((new CSpan('«'))->addClass('page-link disabled'));
        $navDiv->addItem((new CSpan('‹'))->addClass('page-link disabled'));
    }
    
    // 页码按钮
    $pageRange = 2;
    $startPage = max(1, $page - $pageRange);
    $endPage = min($totalPages, $page + $pageRange);
    
    if ($startPage > 1) {
        $navDiv->addItem((new CLink('1', buildPageUrl($data, 1)))->addClass('page-link'));
        if ($startPage > 2) {
            $navDiv->addItem((new CSpan('...'))->addClass('page-ellipsis'));
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $page) {
            $navDiv->addItem((new CSpan($i))->addClass('page-link current'));
        } else {
            $navDiv->addItem((new CLink($i, buildPageUrl($data, $i)))->addClass('page-link'));
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $navDiv->addItem((new CSpan('...'))->addClass('page-ellipsis'));
        }
        $navDiv->addItem((new CLink($totalPages, buildPageUrl($data, $totalPages)))->addClass('page-link'));
    }
    
    // 下一页和末页
    if ($page < $totalPages) {
        $navDiv->addItem((new CLink('›', buildPageUrl($data, $page + 1)))->addClass('page-link')->setAttribute('title', LanguageManager::t('Next Page')));
        $navDiv->addItem((new CLink('»', buildPageUrl($data, $totalPages)))->addClass('page-link')->setAttribute('title', LanguageManager::t('Last Page')));
    } else {
        $navDiv->addItem((new CSpan('›'))->addClass('page-link disabled'));
        $navDiv->addItem((new CSpan('»'))->addClass('page-link disabled'));
    }
    
    // 右侧：每页数量和跳转
    $controlsDiv = (new CDiv())->addClass('pagination-controls');
    
    // 每页数量选择
    $perPageSelect = new CTag('select', true);
    $perPageSelect->setAttribute('id', 'per-page-select');
    $perPageSelect->setAttribute('onchange', 'changePerPage(this.value)');
    $perPageSelect->addClass('per-page-select');
    
    foreach ($allowedPerPage as $value) {
        $opt = new CTag('option', true, $value . ' ' . LanguageManager::t('per page'));
        $opt->setAttribute('value', $value);
        if ($perPage == $value) {
            $opt->setAttribute('selected', 'selected');
        }
        $perPageSelect->addItem($opt);
    }
    $controlsDiv->addItem($perPageSelect);
    
    // 跳转输入
    $jumpDiv = (new CDiv())->addClass('page-jump');
    $jumpDiv->addItem(new CSpan(LanguageManager::t('Go to') . ': '));
    $jumpDiv->addItem(
        (new CTextBox('jump_page', ''))
            ->setAttribute('id', 'jump-page-input')
            ->setAttribute('type', 'number')
            ->setAttribute('min', '1')
            ->setAttribute('max', $totalPages)
            ->setAttribute('placeholder', $page)
            ->addClass('jump-input')
    );
    $jumpDiv->addItem(
        (new CButton('jump_btn', LanguageManager::t('Go')))
            ->setAttribute('onclick', 'jumpToPage()')
            ->addClass('jump-btn')
    );
    $controlsDiv->addItem($jumpDiv);
    
    $container->addItem($infoDiv);
    $container->addItem($navDiv);
    $container->addItem($controlsDiv);
    
    return $container;
}

/**
 * 获取主机分组状态显示元素
 */
function getGroupStatusDisplay($group) {
    $hostCount = $group['host_count'];
    $totalCpu = $group['total_cpu'];
    $totalMemory = $group['total_memory'];

    if ($hostCount == 0) {
        $statusText = '📂 ' . LanguageManager::t('Empty Group');
        $statusClass = 'status-empty';
    } elseif ($totalCpu > 0 || $totalMemory > 0) {
        $statusText = '🖥️ ' . LanguageManager::t('Active Group');
        $statusClass = 'status-active';
    } else {
        $statusText = '📋 ' . LanguageManager::t('Basic Group');
        $statusClass = 'status-basic';
    }

    return (new CSpan($statusText))->addClass($statusClass)->setAttribute('style', 'font-size: 12px;');
}

// ============ 页面渲染开始 ============

$pageTitle = $data['title'] ?? 'Host Groups';

// CSS 样式
$styleTag = new CTag('style', true, '
.cmdb-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}

.search-form-container {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    align-items: end;
}

.form-field {
    display: flex;
    flex-direction: column;
}

.form-field label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
    font-size: 13px;
}

.form-field input {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s;
    background-color: #fff;
    height: 38px;
    box-sizing: border-box;
}

.form-field input:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* 统计卡片 */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-icon {
    font-size: 2rem;
    flex-shrink: 0;
    margin-right: 15px;
}

.stat-content {
    text-align: right;
    flex: 1;
}

.stat-number {
    font-size: 1.6rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* 分页组件 */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    flex-wrap: wrap;
    gap: 15px;
    border-top: 1px solid #dee2e6;
    margin-top: 10px;
}

.pagination-info {
    color: #6c757d;
    font-size: 14px;
}

.pagination-nav {
    display: flex;
    align-items: center;
    gap: 4px;
}

.page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #fff;
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.15s;
}

.page-link:hover {
    background-color: #e9ecef;
    text-decoration: none;
}

.page-link.current {
    background-color: #007bff;
    border-color: #007bff;
    color: #fff;
}

.page-link.disabled {
    color: #6c757d;
    pointer-events: none;
    background-color: #f8f9fa;
}

.page-ellipsis {
    padding: 0 8px;
    color: #6c757d;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.per-page-select {
    padding: 6px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    background-color: #fff;
}

.page-jump {
    display: flex;
    align-items: center;
    gap: 6px;
}

.page-jump span {
    color: #6c757d;
    font-size: 13px;
}

.jump-input {
    width: 60px;
    padding: 6px 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    text-align: center;
}

.jump-btn {
    padding: 6px 12px;
    border: 1px solid #007bff;
    border-radius: 4px;
    background-color: #007bff;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
}

.jump-btn:hover {
    background-color: #0056b3;
}

/* 表格 */
.groups-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.groups-table thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dee2e6;
}

.groups-table tbody td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: middle;
}

.groups-table tbody tr:hover {
    background-color: #f8f9fa;
}

.groups-table tbody tr:last-child td {
    border-bottom: none;
}

.group-link {
    color: #007bff;
    text-decoration: none;
}

.group-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.sort-link {
    color: #495057;
    text-decoration: none;
    font-weight: 600;
}

.sort-link:hover {
    color: #007bff;
}

.status-empty { color: #6c757d; font-weight: 600; }
.status-active { color: #007bff; font-weight: 600; }
.status-basic { color: #ffc107; font-weight: 600; }

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}

.stat-unit {
    font-size: 0.8rem;
    color: #6c757d;
}
');

// 创建主体内容
$content = (new CDiv())->addClass('cmdb-container');

// 搜索表单
$searchForm = (new CForm())
    ->setMethod('get')
    ->setAction('zabbix.php')
    ->addItem((new CInput('hidden', 'action', 'cmdb.groups')))
    ->addItem((new CInput('hidden', 'page', '1')))
    ->addItem((new CInput('hidden', 'per_page', $data['per_page'] ?? 25)));

$searchForm->addItem(
    (new CDiv())
        ->addClass('search-form')
        ->addItem(
            (new CDiv())
                ->addClass('form-field')
                ->addItem(new CLabel('🔍 ' . LanguageManager::t('Search by group name')))
                ->addItem(
                    (new CTextBox('search', $data['search'] ?? ''))
                        ->setAttribute('placeholder', LanguageManager::t('Search groups...'))
                        ->setAttribute('oninput', 'handleSearchInput(this)')
                )
        )
);

$content->addItem((new CDiv())->addClass('search-form-container')->addItem($searchForm));

// 统计卡片
$statsContainer = (new CDiv())->addClass('stats-container');

$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('🗂️'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['total_groups'] ?? 0))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Total Groups')))->addClass('stat-label'))
        )
);

$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['grand_total_hosts'] ?? 0))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Total Hosts')))->addClass('stat-label'))
        )
);

$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('💻'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['grand_total_cpu'] ?? 0))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('CPU Total')))->addClass('stat-label'))
        )
);

$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('💾'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv(ItemFinder::formatMemorySize($data['grand_total_memory'] ?? 0)))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Memory Total')))->addClass('stat-label'))
        )
);

$content->addItem($statsContainer);

// 顶部分页
$content->addItem(createPagination($data));

// 创建表格
$table = new CTable();
$table->addClass('groups-table');

$header = [
    createSortLink(LanguageManager::t('Group Name'), 'name', $data),
    createSortLink(LanguageManager::t('Host Count'), 'host_count', $data),
    createSortLink(LanguageManager::t('CPU Total'), 'total_cpu', $data),
    createSortLink(LanguageManager::t('Memory Total'), 'total_memory', $data),
    LanguageManager::t('Status')
];
$table->setHeader($header);

if (empty($data['groups'])) {
    $table->addRow([
        (new CCol(LanguageManager::t('No groups found')))
            ->addClass('no-data')
            ->setAttribute('colspan', 5)
    ]);
} else {
    foreach ($data['groups'] as $group) {
        // 分组名称
        $groupNameCol = new CCol();
        $groupNameCol->addItem(
            (new CLink(htmlspecialchars($group['name']), 'zabbix.php?action=cmdb&groupid=' . $group['groupid']))
                ->addClass('group-link')
        );

        // 主机数量
        $hostCountCol = new CCol();
        $hostCountCol->addItem((new CSpan($group['host_count']))->setAttribute('style', 'font-weight: 600;'));
        $hostCountCol->addItem(' ');
        $hostCountCol->addItem((new CSpan(LanguageManager::t('hosts')))->addClass('stat-unit'));

        // CPU总量
        $cpuCol = new CCol();
        if ($group['total_cpu'] > 0) {
            $cpuCol->addItem((new CSpan($group['total_cpu']))->setAttribute('style', 'font-weight: 600; color: #4f46e5;'));
            $cpuCol->addItem(' ');
            $cpuCol->addItem((new CSpan(LanguageManager::t('cores')))->addClass('stat-unit'));
        } else {
            $cpuCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存总量
        $memoryCol = new CCol();
        if ($group['total_memory'] > 0) {
            $memoryCol->addItem((new CSpan(ItemFinder::formatMemorySize($group['total_memory'])))->setAttribute('style', 'font-weight: 600; color: #059669;'));
        } else {
            $memoryCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 状态
        $statusCol = new CCol();
        $statusCol->addItem(getGroupStatusDisplay($group));

        $table->addRow([
            $groupNameCol,
            $hostCountCol,
            $cpuCol,
            $memoryCol,
            $statusCol
        ]);
    }
}

$content->addItem($table);

// 底部分页
$content->addItem(createPagination($data));

// JavaScript
$jsData = json_encode([
    'page' => $data['page'],
    'per_page' => $data['per_page'],
    'total_pages' => $data['total_pages'],
    'search' => $data['search'] ?? '',
    'sort' => $data['sort'] ?? 'host_count',
    'sortorder' => $data['sortorder'] ?? 'DESC',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// 使用 CScriptTag 避免 HTML 编码问题
$jsCode = <<<JAVASCRIPT
window.cmdbPageData = {$jsData};
window.cmdbPageData.action = "cmdb.groups";
JAVASCRIPT;

$content->addItem(new CScriptTag($jsCode));

// 加载外部 JS 文件
$content->addItem((new CTag('script', true))
    ->setAttribute('src', 'modules/zabbix_cmdb/assets/js/cmdb.js')
    ->setAttribute('type', 'text/javascript'));

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $content);
