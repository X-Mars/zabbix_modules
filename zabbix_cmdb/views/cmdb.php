<?php

// 引入语言管理器
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;

// 创建页面
$page = new CHtmlPage();
$page->setTitle(LanguageManager::t('CMDB'));

// 添加自定义CSS
$page->addItem((new CTag('style', true, '
.cmdb-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}
.cmdb-header {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}
.search-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}
.search-form .form-field {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}
.search-form .form-field label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #495057;
}
.search-form .form-field input,
.search-form .form-field select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
.search-form .form-field input:focus,
.search-form .form-field select:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
.btn-search {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}
.btn-search:hover {
    background-color: #0056b3;
}
.btn-clear {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}
.btn-clear:hover {
    background-color: #545b62;
}
.hosts-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow: hidden;
}
.hosts-table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: bold;
    padding: 12px 8px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-size: 14px;
}
.hosts-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
    vertical-align: top;
}
.hosts-table tr:nth-child(even) {
    background-color: #f8f9fa;
}
.hosts-table tr:hover {
    background-color: #e9ecef;
}
.host-link {
    color: #007bff;
    text-decoration: none;
}
.host-link:hover {
    text-decoration: underline;
}
.interface-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
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
.no-data {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}
.loading {
    text-align: center;
    padding: 40px;
    color: #007bff;
}
')));

// 创建搜索表单
$form = (new CDiv())
    ->addClass('cmdb-header')
    ->addItem(
        (new CForm())
            ->setMethod('get')
            ->setAction('zabbix.php')
            ->addItem(
                (new CDiv())
                    ->addClass('search-form')
                    ->addItem(
                        (new CDiv())
                            ->addClass('form-field')
                            ->addItem(new CLabel(LanguageManager::t('Search by hostname or IP')))
                            ->addItem(
                                (new CTextBox('search', $data['search']))
                                    ->setAttribute('placeholder', LanguageManager::t('Search hosts...'))
                            )
                    )
                    ->addItem(
                        (new CDiv())
                            ->addClass('form-field')
                            ->addItem(new CLabel(LanguageManager::t('Select host group')))
                            ->addItem(
                                (new CComboBox('groupid', $data['selected_groupid']))
                                    ->addItem(0, LanguageManager::t('All Groups'))
                                    ->addItems(array_column($data['host_groups'], 'name', 'groupid'))
                            )
                    )
                    ->addItem(
                        (new CButton('filter', LanguageManager::t('Search')))
                            ->addClass('btn-search')
                            ->setType('submit')
                    )
                    ->addItem(
                        (new CButton('clear', LanguageManager::t('Clear')))
                            ->addClass('btn-clear')
                            ->setType('button')
                            ->setAttribute('onclick', 'clearFilters()')
                    )
            )
            ->addItem((new CInput('hidden', 'action', 'cmdb')))
    );

// 创建主机表格
$table = (new CTableInfo())
    ->addClass('hosts-table')
    ->setHeader([
        LanguageManager::t('Host Name'),
        LanguageManager::t('IP Address'),
        LanguageManager::t('Interface Type'),
        LanguageManager::t('CPU Total'),
        LanguageManager::t('Memory Total'),
        LanguageManager::t('Host Group')
    ]);

if (empty($data['hosts'])) {
    $table->addRow(
        (new CCol(LanguageManager::t('No hosts found')))
            ->addClass('no-data')
            ->setColSpan(6)
    );
} else {
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

        // 获取主机分组
        $groupNames = array_column($host['groups'], 'name');

        $table->addRow([
            (new CLink($host['name'], 'zabbix.php?action=host.view&hostid=' . $host['hostid']))
                ->addClass('host-link'),
            $mainIp,
            !empty($interfaceTypes) ? $interfaceTypes : '-',
            $host['cpu_total'],
            $host['memory_total'],
            implode(', ', $groupNames)
        ]);
    }
}

// 添加JavaScript
$page->addItem((new CTag('script', true, '
function clearFilters() {
    document.querySelector(\'input[name="search"]\').value = "";
    document.querySelector(\'select[name="groupid"]\').value = "0";
    document.querySelector(\'form\').submit();
}
')));

// 构建页面内容
$page->addItem(
    (new CDiv())
        ->addClass('cmdb-container')
        ->addItem(
            (new CDiv())
                ->addItem(new CTag('h1', true, LanguageManager::t('Configuration Management Database')))
        )
        ->addItem($form)
        ->addItem($table)
);

echo $page->show();
