<?php

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

/**
 * 获取主机分组状态显示元素
 */
function getGroupStatusDisplay($group) {
    $hostCount = $group['host_count'];
    $totalCpu = $group['total_cpu'];
    $totalMemory = $group['total_memory'];

    $statusText = '';
    $statusClass = 'status-normal';

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

    return (new CSpan($statusText))
        ->addClass($statusClass)
        ->setAttribute('style', 'font-size: 12px;');
}

/**
 * 创建排序链接
 */
function createSortLink($title, $field, $data) {
    $currentSort = isset($data['sort']) ? $data['sort'] : '';
    $currentOrder = isset($data['sortorder']) ? $data['sortorder'] : 'ASC';

    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';

    $icon = '';
    if ($currentSort === $field) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }

    // 构建URL，包含搜索参数
    $url = 'zabbix.php?action=cmdb.groups&sort=' . $field . '&sortorder=' . $newOrder;
    if (!empty($data['search'])) {
        $url .= '&search=' . urlencode($data['search']);
    }

    $link = (new CLink($title . $icon, $url))
        ->addClass('sort-link');

    return $link;
}

// 从控制器获取标题
$pageTitle = $data['title'] ?? 'Host Groups';

// 添加与Zabbix主题一致的CSS
$styleTag = new CTag('style', true, '
.cmdb-container {
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 15px;
    align-items: end;
    margin-bottom: 20px;
    padding: 20px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
        gap: 10px;
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

.form-field input {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out;
    background-color: #fff;
    height: 38px;
    box-sizing: border-box;
}

.form-field input:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 400;
    text-align: center;
    vertical-align: middle;
    user-select: none;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    color: #fff;
    background-color: #0056b3;
    border-color: #004085;
}

.btn-secondary {
    color: #6c757d;
    background-color: transparent;
    border-color: #6c757d;
}

.btn-secondary:hover {
    color: #fff;
    background-color: #6c757d;
    border-color: #545b62;
}

.groups-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    table-layout: fixed;
    overflow: visible;
}

.groups-table thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dee2e6;
    max-width: 300px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: visible;
    min-height: 20px;
    line-height: 1.4;
}

.groups-table tbody td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: top;
    max-width: 300px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: hidden;
    min-height: 20px;
    line-height: 1.4;
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

.status-normal {
    color: #28a745;
    font-weight: 600;
}

.status-empty {
    color: #6c757d;
    font-weight: 600;
}

.status-active {
    color: #007bff;
    font-weight: 600;
}

.status-basic {
    color: #ffc107;
    font-weight: 600;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}

.stat-unit {
    font-size: 0.875rem;
    color: #6c757d;
}

.sort-link {
    color: #495057;
    text-decoration: none;
    font-weight: 600;
}

.sort-link:hover {
    color: #007bff;
    text-decoration: underline;
}
');

// 创建主体内容
$content = (new CDiv())
    ->addClass('cmdb-container');

// 添加搜索表单
$content->addItem(
    (new CForm())
        ->setAttribute('method', 'get')
        ->setAttribute('action', 'zabbix.php')
        ->addItem(
            (new CDiv())
                ->addClass('search-form')
                ->addItem(
                    (new CDiv())
                        ->addClass('form-field')
                        ->addItem(new CLabel('🔍 ' . LanguageManager::t('Search by group name')))
                        ->addItem(
                            (new CTextBox('search', isset($data['search']) ? $data['search'] : ''))
                                ->setAttribute('placeholder', LanguageManager::t('Search groups...'))
                                ->setAttribute('oninput', 'handleSearchInput(this)')
                        )
                )
                ->addItem(
                    (new CButton('submit', '🔍 ' . LanguageManager::t('Search')))
                        ->addClass('btn btn-primary')
                )
        )
        ->addItem((new CInput('hidden', 'action', 'cmdb.groups')))
);

// 创建表格
$table = new CTable();
$table->addClass('groups-table');

// 添加表头
$header = [
    LanguageManager::t('Group Name'),
    createSortLink(LanguageManager::t('Host Count'), 'host_count', $data),
    createSortLink(LanguageManager::t('CPU Total'), 'total_cpu', $data),
    createSortLink(LanguageManager::t('Memory Total'), 'total_memory', $data),
    LanguageManager::t('Status')
];
$table->setHeader($header);

// 如果没有分组数据
if (empty($data['groups'])) {
    $table->addRow([
        (new CCol(LanguageManager::t('No groups found')))
            ->addClass('no-data')
            ->setAttribute('colspan', 5)
    ]);
} else {
    // 添加分组数据行
    foreach ($data['groups'] as $group) {
        // 分组名称
        $groupNameCol = new CCol();
        $groupNameCol->addItem(
            (new CLink(htmlspecialchars($group['name']), 'zabbix.php?action=hostgroup.edit&groupid=' . $group['groupid']))
                ->addClass('group-link')
        );

        // 主机数量
        $hostCountCol = new CCol();
        $hostCountCol->addItem(
            (new CSpan($group['host_count']))
                ->addClass('stat-number')
        );
        $hostCountCol->addItem(
            (new CSpan(LanguageManager::t('hosts')))
                ->addClass('stat-unit')
        );

        // CPU总量
        $cpuCol = new CCol();
        if ($group['total_cpu'] > 0) {
            $cpuCol->addItem(
                (new CSpan($group['total_cpu']))
                    ->addClass('stat-number')
                    ->setAttribute('style', 'color: #4f46e5;')
            );
            $cpuCol->addItem(
                (new CSpan(LanguageManager::t('cores')))
                    ->addClass('stat-unit')
            );
        } else {
            $cpuCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存总量
        $memoryCol = new CCol();
        if ($group['total_memory'] > 0) {
            $memoryCol->addItem(
                (new CSpan(ItemFinder::formatMemorySize($group['total_memory'])))
                    ->addClass('stat-number')
                    ->setAttribute('style', 'color: #059669;')
            );
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

// 添加JavaScript
$content->addItem(new CTag('script', true, '
// 添加自动搜索功能
// 全局变量用于防抖
var searchTimeout;

function handleSearchInput(input) {
    clearTimeout(searchTimeout);
    var form = input.closest("form");

    searchTimeout = setTimeout(function() {
        if (form) {
            form.submit();
        }
    }, 500);
}

document.addEventListener("DOMContentLoaded", function() {
    // 可以在这里添加额外的初始化逻辑
    var searchInput = document.querySelector("input[name=\"search\"]");
});
'));

// 使用兼容渲染器显示页面（模块视图需要直接输出，不能返回）
ViewRenderer::render($pageTitle, $styleTag, $content);