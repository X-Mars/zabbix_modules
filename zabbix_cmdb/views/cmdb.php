<?php

// 引入语言管理器
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

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
    $url = 'zabbix.php?action=cmdb&sort=' . $field . '&sortorder=' . $newOrder;
    if (!empty($data['search'])) {
        $url .= '&search=' . urlencode($data['search']);
    }
    if (!empty($data['selected_groupid'])) {
        $url .= '&groupid=' . $data['selected_groupid'];
    }
    if (!empty($data['selected_interface_type'])) {
        $url .= '&interface_type=' . $data['selected_interface_type'];
    }

    $link = (new CLink($title . $icon, $url))
        ->addClass('sort-link');

    return $link;
}

/**
 * 获取主机状态显示元素
 */
function getHostStatusDisplay($host) {
    // 获取主机状态信息
    $statusInfo = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
    
    // 如果主机被禁用，显示Disabled
    if ($host['status'] == 1) {
        $statusText = '🚫 Disabled';
        $statusClass = 'status-disabled';
    } 
    // 如果主机在维护中，显示Maintenance
    elseif (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
        $statusText = '🔧 Maintenance';
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
        $statusText = $icon . ' ' . $statusInfo['text'];
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

// 使用Zabbix原生的页面结构
$page = new CHtmlPage();
$page->setTitle('🗂️ ' . LanguageManager::t('CMDB'));

// 构建下拉框选项 - 使用CTag直接生成select元素

// 添加与Zabbix主题一致的CSS
$page->addItem((new CTag('style', true, '
.cmdb-container {
    padding: 20px;
    max-width: 1600px;
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
    grid-template-columns: 1fr 1fr 1fr auto;
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
    table-layout: fixed;
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
    max-width: 300px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: visible;
    min-height: 20px;
    line-height: 1.4;
}

.hosts-table tbody td {
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
    max-height: 55px; /* 3行文字高度 */
    position: relative;
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
')));

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
                                        $select->setAttribute('onchange', 'handleInterfaceChange(this)');

                                        // 添加"所有接口"选项
                                        $optAll = new CTag('option', true, LanguageManager::t('All Interfaces'));
                                        $optAll->setAttribute('value', '0');
                                        $select->addItem($optAll);

                                        // 接口类型选项
                                        $interfaceTypes = [
                                            1 => LanguageManager::t('Agent'),
                                            2 => LanguageManager::t('SNMP'),
                                            3 => LanguageManager::t('IPMI'),
                                            4 => LanguageManager::t('JMX')
                                        ];

                                        foreach ($interfaceTypes as $typeId => $typeName) {
                                            $opt = new CTag('option', true, $typeName);
                                            $opt->setAttribute('value', $typeId);
                                            if (isset($data['selected_interface_type']) && $data['selected_interface_type'] == $typeId) {
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
if (!empty($data['hosts'])) {
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
                            ->addItem((new CDiv(count($data['hosts'])))->addClass('stat-number'))
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
                            ->addItem((new CDiv(countActiveHosts($data['hosts'])))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Active Hosts')))->addClass('stat-label'))
                    )
            )
    );
}

// 创建表格
$table = new CTable();
$table->addClass('hosts-table');

// 添加表头
$header = [
    createSortLink(LanguageManager::t('Host Name'), 'host', $data),
    createSortLink(LanguageManager::t('System Name'), 'system_name', $data),
    LanguageManager::t('IP Address'),
    LanguageManager::t('Architecture'),
    LanguageManager::t('Interface Type'),
    createSortLink(LanguageManager::t('CPU Total'), 'cpu_total', $data),
    createSortLink(LanguageManager::t('CPU Usage'), 'cpu_usage', $data),
    createSortLink(LanguageManager::t('Memory Total'), 'memory_total', $data),
    createSortLink(LanguageManager::t('Memory Usage'), 'memory_usage', $data),
    LanguageManager::t('Operating System'),
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
                (new CSpan(LanguageManager::t('cores')))->setAttribute('style', 'color: #6c757d; font-size: 12px;')
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

function handleGroupChange(select) {
    var form = select.closest("form");

    if (form) {
        form.submit();
    }
}

function handleInterfaceChange(select) {
    var form = select.closest("form");

    if (form) {
        form.submit();
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // 可以在这里添加额外的初始化逻辑
    var searchInput = document.querySelector("input[name=\"search\"]");
    var groupSelect = document.getElementById("groupid-select");
    var interfaceSelect = document.getElementById("interface-type-select");
});
'));

$page->addItem($content);
$page->show();

