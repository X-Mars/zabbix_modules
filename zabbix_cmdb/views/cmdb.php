<?php

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

/**
 * 构建保留所有筛选和分页参数的 URL
 */
function buildCmdbUrl($params, $data) {
    $url = 'zabbix.php?action=cmdb';
    foreach ($params as $key => $value) {
        $url .= '&' . $key . '=' . urlencode($value);
    }
    // 保留搜索条件
    if (!isset($params['search']) && !empty($data['search'])) {
        $url .= '&search=' . urlencode($data['search']);
    }
    if (!isset($params['groupid']) && !empty($data['selected_groupid'])) {
        $url .= '&groupid=' . $data['selected_groupid'];
    }
    if (!isset($params['interface_type']) && !empty($data['interface_type'])) {
        $url .= '&interface_type=' . $data['interface_type'];
    }
    if (!isset($params['page_size']) && isset($data['page_size'])) {
        $url .= '&page_size=' . $data['page_size'];
    }
    return $url;
}

/**
 * 创建排序链接（切换排序后重置到第1页）
 */
function createSortLink($title, $field, $data) {
    $currentSort = isset($data['sort']) ? $data['sort'] : '';
    $currentOrder = isset($data['sortorder']) ? $data['sortorder'] : 'ASC';

    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';

    $icon = '';
    if ($currentSort === $field) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }

    $url = buildCmdbUrl([
        'sort'      => $field,
        'sortorder' => $newOrder,
        'page'      => 1,
    ], $data);

    return new CLink($title . $icon, $url);
}

/**
 * 创建分页链接
 */
function createPaginationLink($pageNum, $data, $text = null, $disabled = false) {
    $label = $text ?? (string)$pageNum;

    if ($disabled || $pageNum == $data['page']) {
        $span = (new CSpan($label))->addClass('page-link');
        if ($pageNum == $data['page'] && $text === null) {
            $span->addClass('page-current');
        }
        if ($disabled) {
            $span->addClass('page-disabled');
        }
        return $span;
    }

    $url = buildCmdbUrl([
        'sort'      => $data['sort'] ?? 'name',
        'sortorder' => $data['sortorder'] ?? 'ASC',
        'page'      => $pageNum,
    ], $data);

    return (new CLink($label, $url))->addClass('page-link');
}

/**
 * 获取主机状态显示元素
 */
function getHostStatusDisplay($host) {
    // 获取主机状态信息
    $statusInfo = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
    
    // 如果主机被禁用，显示Disabled
    if ($host['status'] == 1) {
        $statusText = '🚫 ' . LanguageManager::t('Disabled');
        $statusClass = 'status-disabled';
    } 
    // 如果主机在维护中，显示Maintenance
    elseif (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
        $statusText = '🔧 ' . LanguageManager::t('Maintenance');
        $statusClass = 'status-maintenance';
    }
    // 否则显示接口可用性状态
    else {
        $icon = '';
        switch ($statusInfo['status']) {
            case 'available':
                $icon = '🟢';
                break;
            case 'unavailable':
                $icon = '🔴';
                break;
            case 'unknown':
            default:
                $icon = '🟡';
                break;
        }
        $statusText = $icon . ' ' . LanguageManager::t($statusInfo['text']);
        $statusClass = $statusInfo['class'];
    }
    
    return (new CSpan($statusText))
        ->addClass($statusClass)
        ->setAttribute('style', 'font-size: 12px;');
}

/**
 * 计算活跃主机数量（基于实际可用性状态）
 */
function countActiveHosts($hosts) {
    $activeCount = 0;
    
    foreach ($hosts as $host) {
        // 如果主机被禁用，跳过
        if ($host['status'] == 1) {
            continue;
        }
        
        // 如果主机在维护中，跳过
        if (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
            continue;
        }
        
        // 检查可用性状态
        $availability = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown'];
        if ($availability['status'] === 'available') {
            $activeCount++;
        }
    }
    
    return $activeCount;
}

// 从控制器获取标题
$pageTitle = $data['title'] ?? 'CMDB';

// 添加与Zabbix主题一致的CSS
$styleTag = new CTag('style', true, '
.cmdb-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}

.cmdb-search-form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto auto;
    gap: 15px;
    align-items: end;
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

.form-field input,
.form-field select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out;
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

.btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 400;
    text-align: center;
    transition: all 0.15s ease-in-out;
    height: 38px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    border-color: #6c757d;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    font-size: 1.8rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hosts-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    table-layout: auto;
    overflow: visible;
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
    overflow: visible;
    line-height: 1.4;
}

.hosts-table tbody td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: top;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: hidden;
    line-height: 1.4;
    max-height: 55px;
    position: relative;
    max-width: 200px;
}

.hosts-table tbody td:hover {
    overflow: visible;
    max-height: none;
    background-color: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 100;
    white-space: normal;
    border-radius: 4px;
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

.interface-agent {
    background-color: #28a745;
    color: white;
}

.interface-snmp {
    background-color: #007bff;
    color: white;
}

.interface-ipmi {
    background-color: #fd7e14;
    color: white;
}

.interface-jmx {
    background-color: #6f42c1;
    color: white;
}

.status-enabled {
    color: #28a745;
    font-weight: 600;
}

.status-disabled {
    color: #dc3545;
    font-weight: 600;
}

.status-available {
    color: #28a745;
    font-weight: 600;
}

.status-unavailable {
    color: #dc3545;
    font-weight: 600;
}

.status-maintenance {
    color: #ffc107;
    font-weight: 600;
}

.status-unknown {
    color: #6c757d;
    font-weight: 600;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}

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

.kernel-display {
    background-color: #fff3cd;
    padding: 3px 6px;
    border-radius: 3px;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 11px;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* ── 分页组件样式 ── */
.pagination-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
    margin-bottom: 20px;
    padding: 12px 16px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.pagination-info {
    font-size: 13px;
    color: #495057;
}
.pagination-info strong {
    color: #212529;
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
    font-size: 13px;
    color: #007bff;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}
.page-link:hover {
    color: #0056b3;
    background-color: #e9ecef;
    border-color: #adb5bd;
    text-decoration: none;
}
.page-link.page-current {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
    cursor: default;
    font-weight: 600;
}
.page-link.page-disabled {
    color: #adb5bd;
    background-color: #f8f9fa;
    border-color: #dee2e6;
    cursor: not-allowed;
}
.page-link.page-disabled:hover {
    color: #adb5bd;
    background-color: #f8f9fa;
}
.page-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    font-size: 13px;
    color: #6c757d;
}
.pagination-size {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #495057;
}
.pagination-size select {
    padding: 4px 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    background-color: #fff;
    cursor: pointer;
}

/* Element Plus inspired visual layer. */
main{min-width:0!important;width:auto!important}
.cmdb-container{box-sizing:border-box;--el-primary:#409eff;--el-primary-light:#ecf5ff;--el-border:#dcdfe6;--el-border-light:#ebeef5;--el-text:#303133;--el-text-secondary:#606266;--el-muted:#909399;--el-success:#67c23a;--el-warning:#e6a23c;--el-danger:#f56c6c;padding:10px;width:100%;max-width:100%;color:var(--el-text)}
.cmdb-search-form{margin-bottom:16px;padding:18px;border:1px solid var(--el-border);border-radius:4px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.search-form{gap:16px}.form-field label{margin-bottom:7px;color:var(--el-text-secondary);font-weight:500}.form-field input,.form-field select{height:34px;padding:0 11px;border-color:var(--el-border);color:var(--el-text);outline:0;transition:border-color .2s,box-shadow .2s}.form-field input:hover,.form-field select:hover{border-color:#c0c4cc}.form-field input:focus,.form-field select:focus{border-color:var(--el-primary);box-shadow:0 0 0 1px var(--el-primary)}
.btn{height:34px;padding:7px 15px;border-radius:4px;transition:all .2s}.btn-primary{border-color:var(--el-primary);background:var(--el-primary)}.btn-primary:hover{border-color:#79bbff;background:#79bbff}.btn-secondary{border-color:var(--el-border);background:#fff;color:var(--el-text-secondary)}.btn-secondary:hover{border-color:#c6e2ff;background:var(--el-primary-light);color:var(--el-primary)}
.stats-container{grid-template-columns:repeat(5,minmax(135px,1fr));gap:12px;margin-bottom:16px}.stat-card{box-sizing:border-box;min-width:0;padding:16px;border-color:var(--el-border);border-radius:4px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:transform .2s,box-shadow .2s}.stat-card:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.09)}.stat-icon{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;margin-right:12px;border-radius:8px;background:var(--el-primary-light);font-size:21px}.stat-number{margin-bottom:3px;color:var(--el-text);font-size:22px;line-height:1.15}.stat-label{color:var(--el-muted);font-size:12px;font-weight:400;letter-spacing:0;text-transform:none}
.hosts-table{border-color:var(--el-border);border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.06)}.hosts-table thead th{padding:11px 9px;border-color:var(--el-border-light);background:#f5f7fa;color:var(--el-text-secondary);font-weight:500}.hosts-table thead th a{color:var(--el-text-secondary);text-decoration:none}.hosts-table thead th a:hover{color:var(--el-primary)}.hosts-table tbody td{padding:11px 9px;border-color:var(--el-border-light);color:var(--el-text-secondary)}.hosts-table tbody tr{transition:background-color .2s}.hosts-table tbody tr:hover{background:#f5f7fa}.hosts-table tbody td:hover{background:inherit;box-shadow:none}.host-link{color:var(--el-primary)}.host-link:hover{color:#79bbff}
.interface-type,.group-tag,.kernel-display{box-sizing:border-box;border-radius:4px;font-weight:500}.interface-type{padding:3px 7px}.interface-agent{background:#f0f9eb;color:#529b2e}.interface-snmp{background:var(--el-primary-light);color:#337ecc}.interface-ipmi{background:#fdf6ec;color:#b88230}.interface-jmx{background:#f4f4f5;color:#73767a}.group-tag{padding:3px 8px;border-color:#a0cfff;background:var(--el-primary-light);color:#337ecc}.kernel-display{border-color:#f3d19e;background:#fdf6ec;color:#b88230}
.status-enabled,.status-available{color:var(--el-success)}.status-disabled,.status-unavailable{color:var(--el-danger)}.status-maintenance{color:var(--el-warning)}.status-unknown{color:var(--el-muted)}.no-data{padding:48px 20px;background:#fff;color:var(--el-muted);font-style:normal}
.pagination-container{margin:12px 0;padding:11px 14px;border-color:var(--el-border);border-radius:4px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.04)}.pagination-info,.pagination-size{color:var(--el-text-secondary)}.page-link{border:0;background:#f4f4f5;color:var(--el-text-secondary);transition:all .2s}.page-link:hover{border:0;background:var(--el-primary-light);color:var(--el-primary)}.page-link.page-current{border:0;background:var(--el-primary);color:#fff}.page-link.page-disabled,.page-link.page-disabled:hover{border:0;background:#f4f4f5;color:#c0c4cc}.pagination-size select{height:30px;border-color:var(--el-border);outline:0}.pagination-size select:focus{border-color:var(--el-primary)}
@media(max-width:1100px){.stats-container{grid-template-columns:repeat(3,minmax(150px,1fr))}}@media(max-width:768px){.cmdb-container{padding:6px}.stats-container{grid-template-columns:repeat(2,minmax(135px,1fr))}.cmdb-search-form{padding:14px}.hosts-table{min-width:1100px}.cmdb-container{overflow-x:auto}}
');

// 创建主体内容
$content = (new CDiv())
    ->addClass('cmdb-container')
    ->addItem(
        (new CDiv())
            ->addClass('cmdb-search-form')
            ->addItem(
                (new CForm())
                    ->setMethod('get')
                    ->setAction('zabbix.php?action=cmdb')
                    ->addItem(
                        (new CDiv())
                            ->addClass('search-form')
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('🔍 ' . LanguageManager::t('Search by hostname or IP')))
                                    ->addItem(
                                        (new CTextBox('search', $data['search']))
                                            ->setAttribute('placeholder', LanguageManager::t('Search hosts...'))
                                            ->setAttribute('oninput', 'handleSearchInput(this)')
                                    )
                            )
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('📂 ' . LanguageManager::t('Select host group')))
                                    ->addItem((function() use ($data) {
                                        $select = new CTag('select', true);
                                        $select->setAttribute('name', 'groupid');
                                        $select->setAttribute('id', 'groupid-select');
                                        $select->setAttribute('onchange', 'handleGroupChange(this)');

                                        // 添加"所有分组"选项
                                        $optAll = new CTag('option', true, LanguageManager::t('All Groups'));
                                        $optAll->setAttribute('value', '0');
                                        $select->addItem($optAll);

                                        // 添加实际的主机组
                                        if (!empty($data['host_groups'])) {
                                            foreach ($data['host_groups'] as $group) {
                                                $opt = new CTag('option', true, $group['name']);
                                                $opt->setAttribute('value', $group['groupid']);
                                                if (isset($data['selected_groupid']) && $data['selected_groupid'] == $group['groupid']) {
                                                    $opt->setAttribute('selected', 'selected');
                                                }
                                                $select->addItem($opt);
                                            }
                                        }

                                        return $select;
                                    })())
                            )
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('🔌 ' . LanguageManager::t('Interface Type')))
                                    ->addItem((function() use ($data) {
                                        $select = new CTag('select', true);
                                        $select->setAttribute('name', 'interface_type');
                                        $select->setAttribute('id', 'interface-type-select');
                                        $select->setAttribute('onchange', 'handleInterfaceTypeChange(this)');

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
                                            $select->addItem($opt);
                                        }

                                        return $select;
                                    })())
                            )
                    )
                    ->addItem((new CInput('hidden', 'action', 'cmdb')))
            )
    );

// 如果有主机数据，添加统计卡片
$totalCount = $data['total_count'] ?? count($data['hosts']);
if (!empty($data['hosts']) || $totalCount > 0) {
    $content->addItem(
        (new CDiv())
            ->addClass('stats-container')
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['total_cpu'] ?? 0))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('CPU Total')))->addClass('stat-label'))
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('💾'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['total_memory'] ? ItemFinder::formatMemorySize($data['total_memory']) : '0 B'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Memory Total')))->addClass('stat-label'))
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($totalCount))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Total Hosts')))->addClass('stat-label'))
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🗂️'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(count($data['host_groups'])))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Host Groups')))->addClass('stat-label'))
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['active_hosts'] ?? 0))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Active Hosts')))->addClass('stat-label'))
                    )
            )
    );
}

// 创建表格
$table = new CTable();
$table->addClass('hosts-table');

// 添加表头（带排序链接）
$header = [
    LanguageManager::t('#'),
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
            ->setAttribute('colspan', 12)
    ]);
} else {
    // 序号起始值：基于分页偏移量
    $rowNum = (($data['page'] ?? 1) - 1) * ($data['page_size'] ?? 50);

    // 添加主机数据行
    foreach ($data['hosts'] as $host) {
        $rowNum++;
        // 获取主要IP地址
        $mainIp = '';
        $interfaceTypes = [];
        foreach ($host['interfaces'] as $interface) {
            if ($interface['main'] == 1) {
                $mainIp = !empty($interface['ip']) ? $interface['ip'] : $interface['dns'];
            }

            // 收集接口类型
            $typeClass = '';
            $typeText = '';
            $typeIcon = '';
            switch ($interface['type']) {
                case 1:
                    $typeClass = 'interface-agent';
                    $typeIcon = '🤖';
                    $typeText = LanguageManager::t('Agent');
                    break;
                case 2:
                    $typeClass = 'interface-snmp';
                    $typeIcon = '📡';
                    $typeText = LanguageManager::t('SNMP');
                    break;
                case 3:
                    $typeClass = 'interface-ipmi';
                    $typeIcon = '🔧';
                    $typeText = LanguageManager::t('IPMI');
                    break;
                case 4:
                    $typeClass = 'interface-jmx';
                    $typeIcon = '☕';
                    $typeText = LanguageManager::t('JMX');
                    break;
            }

            if (!empty($typeText)) {
                $interfaceTypes[] = (new CSpan($typeText))->addClass('interface-type ' . $typeClass);
            }
        }        // 获取主机分组
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
        $hostNameCol->addItem(
            (new CDiv())
                ->addItem(
                    getHostStatusDisplay($host)
                )
        );

        // 系统名称
        $systemNameCol = new CCol();
        if (isset($host['system_name']) && $host['system_name'] !== null) {
            $systemNameCol->addItem(
                (new CSpan(htmlspecialchars($host['system_name'])))->setAttribute('style', 'font-family: monospace; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
            );
        } else {
            $systemNameCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // IP地址
        $ipCol = new CCol(
            (new CSpan(htmlspecialchars($mainIp)))->addClass('code-display')->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
        );

        // 架构
        $archCol = new CCol();
        if (isset($host['os_architecture']) && $host['os_architecture'] !== null) {
            $archCol->addItem(
                (new CSpan(htmlspecialchars($host['os_architecture'])))->setAttribute('style', 'font-family: monospace; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
            );
        } else {
            $archCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 接口类型
        $interfaceCol = new CCol();
        if (!empty($interfaceTypes)) {
            $interfaceContainer = (new CDiv())->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;');
            foreach ($interfaceTypes as $interfaceType) {
                $interfaceContainer->addItem($interfaceType);
            }
            $interfaceCol->addItem($interfaceContainer);
        } else {
            $interfaceCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // CPU总量
        $cpuCol = new CCol();
        if ($host['cpu_total'] !== '-') {
            $cpuCol->addItem([
                (new CSpan(htmlspecialchars($host['cpu_total'])))->setAttribute('style', 'font-weight: 600; color: #4f46e5;'),
                ' ',
                (new CSpan(LanguageManager::t((float) $host['cpu_total'] == 1 ? 'core' : 'cores')))->setAttribute('style', 'color: #6c757d; font-size: 12px;')
            ]);
        } else {
            $cpuCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // CPU使用率
        $cpuUsageCol = new CCol();
        if ($host['cpu_usage'] !== '-') {
            $usageValue = floatval(str_replace('%', '', $host['cpu_usage']));
            $usageColor = '#28a745'; // 绿色
            $usageIcon = '🟢'; // 正常
            if ($usageValue > 80) {
                $usageColor = '#dc3545'; // 红色
                $usageIcon = '🔴'; // 高负载
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107'; // 黄色
                $usageIcon = '🟡'; // 中等负载
            }
            $cpuUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . htmlspecialchars($host['cpu_usage'])))->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $cpuUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存总量
        $memoryCol = new CCol();
        if ($host['memory_total'] !== '-') {
            $memoryCol->addItem(
                (new CSpan(htmlspecialchars($host['memory_total'])))->setAttribute('style', 'font-weight: 600; color: #059669;')
            );
        } else {
            $memoryCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 内存使用率
        $memoryUsageCol = new CCol();
        if ($host['memory_usage'] !== '-') {
            $usageValue = floatval(str_replace('%', '', $host['memory_usage']));
            $usageColor = '#28a745'; // 绿色
            $usageIcon = '🟢'; // 正常
            if ($usageValue > 80) {
                $usageColor = '#dc3545'; // 红色
                $usageIcon = '🔴'; // 高负载
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107'; // 黄色
                $usageIcon = '🟡'; // 中等负载
            }
            $memoryUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . htmlspecialchars($host['memory_usage'])))->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $memoryUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 操作系统
        $osCol = new CCol();
        if (isset($host['operating_system']) && $host['operating_system'] !== null) {
            $osName = $host['operating_system'];
            $osIcon = '💻'; // 默认图标
            
            // 根据操作系统类型设置图标
            if (stripos($osName, 'windows') !== false) {
                $osIcon = '🪟';
            } elseif (stripos($osName, 'linux') !== false) {
                $osIcon = '🐧';
            } elseif (stripos($osName, 'ubuntu') !== false) {
                $osIcon = '🟠';
            } elseif (stripos($osName, 'centos') !== false || stripos($osName, 'red hat') !== false) {
                $osIcon = '🔴';
            } elseif (stripos($osName, 'debian') !== false) {
                $osIcon = '🔵';
            } elseif (stripos($osName, 'mac') !== false || stripos($osName, 'darwin') !== false) {
                $osIcon = '🍎';
            } elseif (stripos($osName, 'freebsd') !== false) {
                $osIcon = '👿';
            } elseif (stripos($osName, 'solaris') !== false) {
                $osIcon = '☀️';
            }
            
            $osCol->addItem(
                (new CSpan(htmlspecialchars($osName)))
                    ->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
                    ->setAttribute('title', htmlspecialchars($osName))
            );
        } else {
            $osCol->addItem((new CSpan('❓ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // 主机分组
        $groupCol = new CCol();
        $groupContainer = (new CDiv())->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;');
        foreach ($groupNames as $groupName) {
            $groupContainer->addItem(
                (new CSpan(htmlspecialchars($groupName)))->addClass('group-tag')
            );
            $groupContainer->addItem(' ');
        }
        $groupCol->addItem($groupContainer);

        $table->addRow([
            (new CCol($rowNum))->setAttribute('style', 'text-align: center; color: #6c757d; font-size: 12px;'),
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

// ── 分页组件（表格上方 + 下方各一份） ──
$page       = $data['page'] ?? 1;
$pageSize   = $data['page_size'] ?? 50;
$totalCount = $data['total_count'] ?? count($data['hosts']);
$totalPages = $data['total_pages'] ?? 1;

/**
 * 构建分页容器（可多次调用以在不同位置插入）
 */
function buildPaginationContainer($page, $pageSize, $totalCount, $totalPages, $data, $idSuffix = '') {
    $startRow = ($page - 1) * $pageSize + 1;
    $endRow   = min($page * $pageSize, $totalCount);

    $paginationContainer = (new CDiv())->addClass('pagination-container');

    // 左侧：分页信息
    $paginationContainer->addItem(
        (new CDiv(
            LanguageManager::tf('Showing %d to %d of %d hosts', $startRow, $endRow, $totalCount)
        ))->addClass('pagination-info')
    );

    // 中间：页码导航
    if ($totalPages > 1) {
        $nav = (new CDiv())->addClass('pagination-nav');

        // « 首页
        $nav->addItem(createPaginationLink(1, $data, '« ' . LanguageManager::t('First'), $page <= 1));
        // ‹ 上一页
        $nav->addItem(createPaginationLink(max(1, $page - 1), $data, '‹ ' . LanguageManager::t('Prev'), $page <= 1));

        // 页码按钮（最多显示 7 个）
        $maxVisible = 7;
        if ($totalPages <= $maxVisible) {
            $startPage = 1;
            $endPage   = $totalPages;
        } else {
            $half = intdiv($maxVisible, 2);
            $startPage = max(1, $page - $half);
            $endPage   = $startPage + $maxVisible - 1;
            if ($endPage > $totalPages) {
                $endPage   = $totalPages;
                $startPage = $endPage - $maxVisible + 1;
            }
        }

        if ($startPage > 1) {
            $nav->addItem(createPaginationLink(1, $data));
            if ($startPage > 2) {
                $nav->addItem((new CSpan('…'))->addClass('page-ellipsis'));
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $nav->addItem(createPaginationLink($i, $data));
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $nav->addItem((new CSpan('…'))->addClass('page-ellipsis'));
            }
            $nav->addItem(createPaginationLink($totalPages, $data));
        }

        // › 下一页
        $nav->addItem(createPaginationLink(min($totalPages, $page + 1), $data, LanguageManager::t('Next') . ' ›', $page >= $totalPages));
        // » 末页
        $nav->addItem(createPaginationLink($totalPages, $data, LanguageManager::t('Last') . ' »', $page >= $totalPages));

        $paginationContainer->addItem($nav);
    }

    // 右侧：每页条数选择
    $sizeSelector = (new CDiv())->addClass('pagination-size');
    $sizeSelector->addItem(new CSpan(LanguageManager::t('Per page')));

    $sizeSelect = new CTag('select', true);
    $sizeSelect->setAttribute('id', 'page-size-select' . $idSuffix);
    $sizeSelect->setAttribute('onchange', 'handlePageSizeChange(this)');

    foreach ([10, 20, 50, 100] as $size) {
        $opt = new CTag('option', true, (string)$size);
        $opt->setAttribute('value', (string)$size);
        if ($size == $pageSize) {
            $opt->setAttribute('selected', 'selected');
        }
        $sizeSelect->addItem($opt);
    }
    $sizeSelector->addItem($sizeSelect);

    $paginationContainer->addItem($sizeSelector);
    return $paginationContainer;
}

// 表格上方分页
if ($totalCount > 0) {
    $content->addItem(buildPaginationContainer($page, $pageSize, $totalCount, $totalPages, $data, '-top'));
}

$content->addItem($table);

// 表格下方分页
if ($totalCount > 0) {
    $content->addItem(buildPaginationContainer($page, $pageSize, $totalCount, $totalPages, $data, '-bottom'));
}

// 添加JavaScript
$content->addItem(new CJsScript('<script>
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

function handleGroupChange(select) {
    var form = select.closest("form");
    if (form) {
        form.submit();
    }
}

function handleInterfaceTypeChange(select) {
    var form = select.closest("form");
    if (form) {
        form.submit();
    }
}

/**
 * 每页条数切换 — 保留当前搜索条件，重置到第 1 页
 */
function handlePageSizeChange(select) {
    var newSize = select.value;
    var params = new URLSearchParams(window.location.search);
    params.set("page_size", newSize);
    params.set("page", "1");            // 切换条数后回到第 1 页
    params.set("action", "cmdb");
    window.location.href = "zabbix.php?" + params.toString();
}

document.addEventListener("DOMContentLoaded", function() {
    // 初始化
    var searchInput = document.querySelector("input[name=\\"search\\"]");
    var groupSelect = document.getElementById("groupid-select");
    var interfaceTypeSelect = document.getElementById("interface-type-select");
});
</script>'));

// 使用兼容渲染器显示页面（模块视图需要直接输出，不能返回）
ViewRenderer::render($pageTitle, $styleTag, $content);
