<?php
/**
 * CMDB 主机列表视图
 * 
 * 功能特性：
 * - 分页组件：页码切换、每页数量切换、页面跳转
 * - 搜索和过滤：按主机名/IP、分组、接口类型
 * - 统计信息：基于筛选条件的CPU/内存总量
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
        'action' => 'cmdb',
        'page' => $page ?? $data['page'],
        'per_page' => $perPage ?? $data['per_page'],
    ];
    
    if (!empty($data['search'])) {
        $params['search'] = $data['search'];
    }
    if (!empty($data['selected_groupid'])) {
        $params['groupid'] = $data['selected_groupid'];
    }
    if (!empty($data['interface_type'])) {
        $params['interface_type'] = $data['interface_type'];
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
    $currentSort = isset($data['sort']) ? $data['sort'] : 'name';
    $currentOrder = isset($data['sortorder']) ? $data['sortorder'] : 'ASC';

    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';

    $icon = '';
    if ($currentSort === $field) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }

    // 构建URL
    $params = [
        'action' => 'cmdb',
        'sort' => $field,
        'sortorder' => $newOrder,
        'page' => 1, // 排序时回到第一页
        'per_page' => $data['per_page'] ?? 25,
    ];
    
    if (!empty($data['search'])) {
        $params['search'] = $data['search'];
    }
    if (!empty($data['selected_groupid'])) {
        $params['groupid'] = $data['selected_groupid'];
    }
    if (!empty($data['interface_type'])) {
        $params['interface_type'] = $data['interface_type'];
    }

    return new CLink($title . $icon, 'zabbix.php?' . http_build_query($params, '', '&amp;'));
}

/**
 * 创建分页组件
 */
function createPagination($data) {
    $page = (int)$data['page'];
    $perPage = (int)$data['per_page'];
    $totalHosts = (int)$data['total_hosts'];
    $totalPages = (int)$data['total_pages'];
    $allowedPerPage = $data['allowed_per_page'] ?? [10, 25, 50, 100];
    
    // 计算显示范围
    $start = ($page - 1) * $perPage + 1;
    $end = min($page * $perPage, $totalHosts);
    
    $container = (new CDiv())->addClass('pagination-container');
    
    // 左侧：显示信息
    $infoText = sprintf(
        LanguageManager::t('Showing %d-%d of %d hosts'),
        $totalHosts > 0 ? $start : 0,
        $end,
        $totalHosts
    );
    $infoDiv = (new CDiv($infoText))->addClass('pagination-info');
    
    // 中间：页码导航
    $navDiv = (new CDiv())->addClass('pagination-nav');
    
    // 首页按钮
    if ($page > 1) {
        $navDiv->addItem(
            (new CLink('«', buildPageUrl($data, 1)))
                ->addClass('page-link')
                ->setAttribute('title', LanguageManager::t('First Page'))
        );
        $navDiv->addItem(
            (new CLink('‹', buildPageUrl($data, $page - 1)))
                ->addClass('page-link')
                ->setAttribute('title', LanguageManager::t('Previous Page'))
        );
    } else {
        $navDiv->addItem((new CSpan('«'))->addClass('page-link disabled'));
        $navDiv->addItem((new CSpan('‹'))->addClass('page-link disabled'));
    }
    
    // 页码按钮
    $pageRange = 2; // 当前页前后显示的页数
    $startPage = max(1, $page - $pageRange);
    $endPage = min($totalPages, $page + $pageRange);
    
    // 如果开始不是1，显示省略号
    if ($startPage > 1) {
        $navDiv->addItem(
            (new CLink('1', buildPageUrl($data, 1)))->addClass('page-link')
        );
        if ($startPage > 2) {
            $navDiv->addItem((new CSpan('...'))->addClass('page-ellipsis'));
        }
    }
    
    // 显示页码范围
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $page) {
            $navDiv->addItem((new CSpan($i))->addClass('page-link current'));
        } else {
            $navDiv->addItem(
                (new CLink($i, buildPageUrl($data, $i)))->addClass('page-link')
            );
        }
    }
    
    // 如果结束不是最后一页，显示省略号
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $navDiv->addItem((new CSpan('...'))->addClass('page-ellipsis'));
        }
        $navDiv->addItem(
            (new CLink($totalPages, buildPageUrl($data, $totalPages)))->addClass('page-link')
        );
    }
    
    // 下一页和末页按钮
    if ($page < $totalPages) {
        $navDiv->addItem(
            (new CLink('›', buildPageUrl($data, $page + 1)))
                ->addClass('page-link')
                ->setAttribute('title', LanguageManager::t('Next Page'))
        );
        $navDiv->addItem(
            (new CLink('»', buildPageUrl($data, $totalPages)))
                ->addClass('page-link')
                ->setAttribute('title', LanguageManager::t('Last Page'))
        );
    } else {
        $navDiv->addItem((new CSpan('›'))->addClass('page-link disabled'));
        $navDiv->addItem((new CSpan('»'))->addClass('page-link disabled'));
    }
    
    // 右侧：每页数量选择和跳转
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
    
    $jumpInput = (new CTextBox('jump_page', ''))
        ->setAttribute('id', 'jump-page-input')
        ->setAttribute('type', 'number')
        ->setAttribute('min', '1')
        ->setAttribute('max', $totalPages)
        ->setAttribute('placeholder', $page)
        ->addClass('jump-input');
    $jumpDiv->addItem($jumpInput);
    
    $jumpBtn = (new CButton('jump_btn', LanguageManager::t('Go')))
        ->setAttribute('onclick', 'jumpToPage()')
        ->addClass('jump-btn');
    $jumpDiv->addItem($jumpBtn);
    
    $controlsDiv->addItem($jumpDiv);
    
    $container->addItem($infoDiv);
    $container->addItem($navDiv);
    $container->addItem($controlsDiv);
    
    return $container;
}

/**
 * 获取主机状态显示元素
 */
function getHostStatusDisplay($host) {
    $statusInfo = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
    
    if ($host['status'] == 1) {
        $statusText = '🚫 Disabled';
        $statusClass = 'status-disabled';
    } elseif (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
        $statusText = '🔧 Maintenance';
        $statusClass = 'status-maintenance';
    } else {
        $icon = '';
        switch ($statusInfo['status']) {
            case 'available':
                $icon = '🟢';
                break;
            case 'unavailable':
                $icon = '🔴';
                break;
            default:
                $icon = '🟡';
                break;
        }
        $statusText = $icon . ' ' . $statusInfo['text'];
        $statusClass = $statusInfo['class'];
    }
    
    return (new CSpan($statusText))
        ->addClass($statusClass)
        ->setAttribute('style', 'font-size: 12px;');
}

/**
 * 计算活跃主机数量
 */
function countActiveHosts($hosts) {
    $activeCount = 0;
    foreach ($hosts as $host) {
        if ($host['status'] == 1) continue;
        if (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) continue;
        $availability = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown'];
        if ($availability['status'] === 'available') {
            $activeCount++;
        }
    }
    return $activeCount;
}

// ============ 页面渲染开始 ============

$pageTitle = $data['title'] ?? 'CMDB';

// CSS 样式
$styleTag = new CTag('style', true, '
/* 基础容器 */
.cmdb-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}

/* 搜索表单 */
.cmdb-search-form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
    align-items: end;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
    }
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

.form-field input,
.form-field select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s;
    background-color: #fff;
    height: 38px;
    box-sizing: border-box;
}

.form-field input:focus,
.form-field select:focus {
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
    border-color: #dee2e6;
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
    transition: background-color 0.15s;
}

.jump-btn:hover {
    background-color: #0056b3;
}

/* 表格 */
.hosts-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    table-layout: fixed;
}

.hosts-table thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dee2e6;
    white-space: nowrap;
}

.hosts-table tbody td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: top;
    max-width: 200px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: hidden;
}

.hosts-table tbody tr:hover {
    background-color: #f8f9fa;
}

.hosts-table tbody tr:last-child td {
    border-bottom: none;
}

.host-link {
    color: #007bff;
    text-decoration: none;
}

.host-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

/* 接口类型标签 */
.interface-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 4px;
    margin-bottom: 2px;
}

.interface-agent { background-color: #28a745; color: white; }
.interface-snmp { background-color: #007bff; color: white; }
.interface-ipmi { background-color: #fd7e14; color: white; }
.interface-jmx { background-color: #6f42c1; color: white; }

/* 状态样式 */
.status-enabled { color: #28a745; font-weight: 600; }
.status-disabled { color: #dc3545; font-weight: 600; }
.status-available { color: #28a745; font-weight: 600; }
.status-unavailable { color: #dc3545; font-weight: 600; }
.status-maintenance { color: #ffc107; font-weight: 600; }
.status-unknown { color: #6c757d; font-weight: 600; }

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}

/* 分组标签 */
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

/* 代码显示 */
.code-display {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 12px;
}
');

// 创建主体内容
$content = (new CDiv())->addClass('cmdb-container');

// 搜索表单
$searchForm = (new CForm())
    ->setMethod('get')
    ->setAction('zabbix.php')
    ->addItem((new CInput('hidden', 'action', 'cmdb')))
    ->addItem((new CInput('hidden', 'page', '1')))
    ->addItem((new CInput('hidden', 'per_page', $data['per_page'] ?? 25)));

$searchGrid = (new CDiv())->addClass('search-form');

// 搜索输入
$searchGrid->addItem(
    (new CDiv())
        ->addClass('form-field')
        ->addItem(new CLabel('🔍 ' . LanguageManager::t('Search by hostname or IP')))
        ->addItem(
            (new CTextBox('search', $data['search'] ?? ''))
                ->setAttribute('placeholder', LanguageManager::t('Search hosts...'))
                ->setAttribute('oninput', 'handleSearchInput(this)')
        )
);

// 分组选择
$groupSelect = new CTag('select', true);
$groupSelect->setAttribute('name', 'groupid');
$groupSelect->setAttribute('id', 'groupid-select');
$groupSelect->setAttribute('onchange', 'handleFilterChange()');

$optAll = new CTag('option', true, LanguageManager::t('All Groups'));
$optAll->setAttribute('value', '0');
$groupSelect->addItem($optAll);

if (!empty($data['host_groups'])) {
    foreach ($data['host_groups'] as $group) {
        $opt = new CTag('option', true, $group['name']);
        $opt->setAttribute('value', $group['groupid']);
        if (isset($data['selected_groupid']) && $data['selected_groupid'] == $group['groupid']) {
            $opt->setAttribute('selected', 'selected');
        }
        $groupSelect->addItem($opt);
    }
}

$searchGrid->addItem(
    (new CDiv())
        ->addClass('form-field')
        ->addItem(new CLabel('📂 ' . LanguageManager::t('Select host group')))
        ->addItem($groupSelect)
);

// 接口类型选择
$interfaceSelect = new CTag('select', true);
$interfaceSelect->setAttribute('name', 'interface_type');
$interfaceSelect->setAttribute('id', 'interface-type-select');
$interfaceSelect->setAttribute('onchange', 'handleFilterChange()');

$interfaceTypes = [
    0 => LanguageManager::t('All Interfaces'),
    1 => LanguageManager::t('Agent'),
    2 => LanguageManager::t('SNMP'),
    3 => LanguageManager::t('IPMI'),
    4 => LanguageManager::t('JMX')
];

foreach ($interfaceTypes as $value => $label) {
    $opt = new CTag('option', true, $label);
    $opt->setAttribute('value', $value);
    if (isset($data['interface_type']) && $data['interface_type'] == $value) {
        $opt->setAttribute('selected', 'selected');
    }
    $interfaceSelect->addItem($opt);
}

$searchGrid->addItem(
    (new CDiv())
        ->addClass('form-field')
        ->addItem(new CLabel('🔌 ' . LanguageManager::t('Interface Type')))
        ->addItem($interfaceSelect)
);

$searchForm->addItem($searchGrid);
$content->addItem((new CDiv())->addClass('cmdb-search-form')->addItem($searchForm));

// 统计卡片 - 使用控制器传来的统计数据
$statsContainer = (new CDiv())->addClass('stats-container');

// CPU总量（基于筛选条件的所有主机）
$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['total_cpu'] ?? 0))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('CPU Total')))->addClass('stat-label'))
        )
);

// 内存总量（基于筛选条件的所有主机）
$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('💾'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['total_memory'] ? ItemFinder::formatMemorySize($data['total_memory']) : '0 B'))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Memory Total')))->addClass('stat-label'))
        )
);

// 总主机数（筛选后的所有主机数量）
$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('📊'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($data['total_hosts'] ?? 0))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Total Hosts')))->addClass('stat-label'))
        )
);

// 主机分组数
$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('🗂️'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv(count($data['host_groups'] ?? [])))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Host Groups')))->addClass('stat-label'))
        )
);

// 当前页活跃主机
$statsContainer->addItem(
    (new CDiv())
        ->addClass('stat-card')
        ->addItem((new CSpan('✅'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv(countActiveHosts($data['hosts'] ?? [])))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('Active (Current Page)')))->addClass('stat-label'))
        )
);

$content->addItem($statsContainer);

// 顶部分页组件
$content->addItem(createPagination($data));

// 创建表格
$table = new CTable();
$table->addClass('hosts-table');

// 表头
$header = [
    createSortLink(LanguageManager::t('Host Name'), 'name', $data),
    createSortLink(LanguageManager::t('System Name'), 'system_name', $data),
    createSortLink(LanguageManager::t('IP Address'), 'ip', $data),
    createSortLink(LanguageManager::t('Architecture'), 'os_architecture', $data),
    LanguageManager::t('Interface Type'),
    createSortLink(LanguageManager::t('CPU Total'), 'cpu_total', $data),
    createSortLink(LanguageManager::t('CPU Usage'), 'cpu_usage', $data),
    createSortLink(LanguageManager::t('Memory Total'), 'memory_total', $data),
    createSortLink(LanguageManager::t('Memory Usage'), 'memory_usage', $data),
    createSortLink(LanguageManager::t('Operating System'), 'operating_system', $data),
    LanguageManager::t('Host Group')
];
$table->setHeader($header);

// 如果没有主机数据
if (empty($data['hosts'])) {
    $table->addRow([
        (new CCol(LanguageManager::t('No hosts found')))
            ->addClass('no-data')
            ->setAttribute('colspan', 11)
    ]);
} else {
    // 添加主机数据行
    foreach ($data['hosts'] as $host) {
        // 获取主要IP地址
        $mainIp = '-';
        $interfaceTypes = [];
        
        if (!empty($host['interfaces'])) {
            foreach ($host['interfaces'] as $interface) {
                if (isset($interface['main']) && $interface['main'] == 1) {
                    $mainIp = !empty($interface['ip']) ? $interface['ip'] : (!empty($interface['dns']) ? $interface['dns'] : '-');
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
                    $interfaceTypes[] = (new CSpan($typeText))->addClass('interface-type ' . $typeClass);
                }
            }
        }

        // 获取主机分组
        $groupNames = [];
        if (isset($host['groups']) && is_array($host['groups'])) {
            $groupNames = array_column($host['groups'], 'name');
        }

        // 主机名和状态
        $hostNameCol = new CCol();
        $hostNameCol->addItem(
            (new CLink(htmlspecialchars($host['name']), 'zabbix.php?action=host.view&hostid=' . $host['hostid']))
                ->addClass('host-link')
        );
        $hostNameCol->addItem((new CDiv())->addItem(getHostStatusDisplay($host)));

        // 系统名称
        $systemNameCol = new CCol();
        if (!empty($host['system_name'])) {
            $systemNameCol->addItem(
                (new CSpan(htmlspecialchars($host['system_name'])))->addClass('code-display')
            );
        } else {
            $systemNameCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // IP地址
        $ipCol = new CCol((new CSpan(htmlspecialchars($mainIp)))->addClass('code-display'));

        // 架构
        $archCol = new CCol();
        if (!empty($host['os_architecture'])) {
            $archCol->addItem((new CSpan(htmlspecialchars($host['os_architecture'])))->addClass('code-display'));
        } else {
            $archCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 接口类型
        $interfaceCol = new CCol();
        if (!empty($interfaceTypes)) {
            $interfaceContainer = new CDiv();
            foreach ($interfaceTypes as $interfaceType) {
                $interfaceContainer->addItem($interfaceType);
            }
            $interfaceCol->addItem($interfaceContainer);
        } else {
            $interfaceCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // CPU总量
        $cpuCol = new CCol();
        if ($host['cpu_total'] !== null) {
            $cpuCol->addItem([
                (new CSpan($host['cpu_total']))->setAttribute('style', 'font-weight: 600; color: #4f46e5;'),
                ' ',
                (new CSpan('cores'))->setAttribute('style', 'color: #6c757d; font-size: 12px;')
            ]);
        } else {
            $cpuCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // CPU使用率
        $cpuUsageCol = new CCol();
        if ($host['cpu_usage'] !== null) {
            $usageValue = floatval($host['cpu_usage']);
            $usageColor = '#28a745';
            $usageIcon = '🟢';
            if ($usageValue > 80) {
                $usageColor = '#dc3545';
                $usageIcon = '🔴';
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107';
                $usageIcon = '🟡';
            }
            $cpuUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . number_format($usageValue, 1) . '%'))
                    ->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $cpuUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存总量
        $memoryCol = new CCol();
        if ($host['memory_total'] !== null) {
            $memoryCol->addItem(
                (new CSpan(ItemFinder::formatMemorySize($host['memory_total'])))
                    ->setAttribute('style', 'font-weight: 600; color: #059669;')
            );
        } else {
            $memoryCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存使用率
        $memoryUsageCol = new CCol();
        if ($host['memory_usage'] !== null) {
            $usageValue = floatval($host['memory_usage']);
            $usageColor = '#28a745';
            $usageIcon = '🟢';
            if ($usageValue > 80) {
                $usageColor = '#dc3545';
                $usageIcon = '🔴';
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107';
                $usageIcon = '🟡';
            }
            $memoryUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . number_format($usageValue, 1) . '%'))
                    ->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $memoryUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 操作系统
        $osCol = new CCol();
        if (!empty($host['operating_system'])) {
            $osCol->addItem(
                (new CSpan(htmlspecialchars($host['operating_system'])))
                    ->setAttribute('title', htmlspecialchars($host['operating_system']))
            );
        } else {
            $osCol->addItem((new CSpan('❓ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 主机分组
        $groupCol = new CCol();
        $groupContainer = new CDiv();
        foreach ($groupNames as $groupName) {
            $groupContainer->addItem((new CSpan(htmlspecialchars($groupName)))->addClass('group-tag'));
            $groupContainer->addItem(' ');
        }
        $groupCol->addItem($groupContainer);

        $table->addRow([
            $hostNameCol,
            $systemNameCol,
            $ipCol,
            $archCol,
            $interfaceCol,
            $cpuCol,
            $cpuUsageCol,
            $memoryCol,
            $memoryUsageCol,
            $osCol,
            $groupCol
        ]);
    }
}

$content->addItem($table);

// 底部分页组件
$content->addItem(createPagination($data));

// JavaScript 数据
$jsData = json_encode([
    'action' => 'cmdb',
    'page' => $data['page'],
    'per_page' => $data['per_page'],
    'total_pages' => $data['total_pages'],
    'search' => $data['search'] ?? '',
    'groupid' => $data['selected_groupid'] ?? 0,
    'interface_type' => $data['interface_type'] ?? 0,
    'sort' => $data['sort'] ?? 'name',
    'sortorder' => $data['sortorder'] ?? 'ASC',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// 使用 heredoc 语法避免 HTML 编码问题
$jsCode = <<<JAVASCRIPT
window.cmdbPageData = {$jsData};
JAVASCRIPT;

$content->addItem(new CScriptTag($jsCode));

// 加载外部 JS 文件
$content->addItem((new CTag('script', true))
    ->setAttribute('src', 'modules/zabbix_cmdb/assets/js/cmdb.js')
    ->setAttribute('type', 'text/javascript'));

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $content);

