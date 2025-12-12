<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;
use Modules\ZabbixGraphTrees\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('Graph Trees');

// 提前定义时间变量，供后续使用
$currentTimeFrom = (int)($data['time_from'] ?? time() - 3600);
$currentTimeTo = (int)($data['time_to'] ?? time());

// 添加CSS样式
$styleTag = new CTag('style', true, '
.graphtrees-container {
    display: flex;
    height: calc(100vh - 120px);
    padding: 0;
    margin: 0;
    gap: 0;
}

.tree-panel {
    width: 300px;
    min-width: 300px;
    border-right: 1px solid #dee2e6;
    background-color: #fff;
    overflow-y: auto;
    padding: 15px;
}

.tree-header {
    padding: 10px 0;
    margin-bottom: 15px;
    border-bottom: 2px solid #007bff;
}

.tree-header h3 {
    margin: 0;
    font-size: 16px;
    color: #495057;
}

.tree-controls {
    margin-top: 10px;
    display: flex;
    gap: 5px;
}

.tree-controls button {
    font-size: 12px;
    padding: 8px 12px;
    height: 32px;
    line-height: 1;
}

.tree-search {
    margin-bottom: 15px;
}

.tree-search input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
}

.tree-node {
    margin: 5px 0;
}

.tree-group {
    margin-bottom: 10px;
}

.tree-group-header {
    padding: 8px 10px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tree-group-header:hover {
    background-color: #e9ecef;
}

.tree-group-icon {
    font-size: 12px;
    transition: transform 0.2s;
}

.tree-group-icon.collapsed {
    transform: rotate(-90deg);
}

.tree-hosts {
    margin-left: 20px;
    margin-top: 5px;
    display: none;
}

.tree-hosts.expanded {
    display: block;
}

.tree-host {
    padding: 6px 10px;
    margin: 2px 0;
    cursor: pointer;
    border-radius: 3px;
    font-size: 13px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tree-host:hover {
    background-color: #e7f3ff;
}

.tree-host.selected {
    background-color: #007bff;
    color: white;
    font-weight: 500;
}

.tree-host-icon {
    font-size: 12px;
}

.content-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #fff;
    overflow: hidden;
}

.content-header {
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

.content-title {
    font-size: 18px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 15px;
}

.filter-panel {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr auto;
    gap: 15px;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
}

.filter-field label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
    font-size: 13px;
}

/* 多选下拉框样式 */
.multi-select-container {
    position: relative;
}

.multi-select-trigger {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    height: 40px;
    background-color: #fff;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    box-sizing: border-box;
}

.multi-select-trigger:hover {
    border-color: #007bff;
}

.multi-select-trigger .trigger-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.multi-select-trigger .trigger-arrow {
    margin-left: 8px;
    font-size: 12px;
    color: #6c757d;
}

.multi-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    display: none;
}

.multi-select-dropdown.show {
    display: block;
}

.multi-select-search {
    padding: 8px;
    border-bottom: 1px solid #dee2e6;
}

.multi-select-search input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
}

.multi-select-actions {
    padding: 8px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    gap: 8px;
}

.multi-select-actions button {
    padding: 4px 10px;
    font-size: 12px;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    background-color: #f8f9fa;
    cursor: pointer;
    color: #212529;
}

.multi-select-actions button:hover {
    background-color: #e9ecef;
}

.multi-select-options {
    padding: 5px 0;
}

.multi-select-option {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.multi-select-option:hover {
    background-color: #f8f9fa;
}

.multi-select-option.selected {
    background-color: #e7f3ff;
}

.multi-select-option input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
}

.multi-select-option label {
    margin: 0;
    cursor: pointer;
    flex: 1;
    font-weight: normal;
}

.auto-refresh-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 40px;
}

.auto-refresh-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.auto-refresh-label {
    font-size: 13px;
    color: #495057;
    cursor: pointer;
}

.auto-refresh-active {
    color: #28a745;
    font-weight: 600;
}

.filter-field select,
.filter-field input {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    height: 40px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6;
}

.btn-secondary:hover {
    background-color: #e9ecef;
}

.content-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.graphs-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 20px;
}

.graph-card {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    background-color: #fff;
}

.graph-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.graph-title {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin: 0;
    flex: 1;
}

.graph-zoom-btn {
    background: none;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 16px;
    color: #6c757d;
    transition: all 0.2s;
    margin-left: 10px;
}

.graph-zoom-btn:hover {
    background-color: #f8f9fa;
    color: #007bff;
    border-color: #007bff;
}

/* 全屏弹窗样式 */
.graph-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.graph-modal {
    background-color: #fff;
    border-radius: 8px;
    width: 90vw;
    height: 85vh;
    max-width: 1600px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.graph-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.graph-modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.graph-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #6c757d;
    padding: 0 5px;
    line-height: 1;
}

.graph-modal-close:hover {
    color: #dc3545;
}

.graph-modal-body {
    flex: 1;
    padding: 20px;
    overflow: hidden;
}

.graph-modal-canvas {
    width: 100%;
    height: 100%;
    position: relative;
}

.graph-canvas {
    width: 100%;
    height: 300px;
    position: relative;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 16px;
    color: #6c757d;
}

.loading-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 时间选择器样式 */
.time-picker-container {
    position: relative;
}

.time-picker-trigger {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    height: 40px;
    background-color: #fff;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    box-sizing: border-box;
}

.time-picker-trigger:hover {
    border-color: #007bff;
}

.time-picker-trigger .trigger-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
}

.time-picker-trigger .trigger-icon {
    margin-left: 8px;
    font-size: 14px;
    color: #6c757d;
}

.time-picker-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    display: none;
    min-width: 320px;
}

.time-picker-dropdown.show {
    display: block;
}

.time-picker-presets {
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
}

.time-picker-presets-title {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
    font-weight: 600;
}

.time-picker-preset-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.time-picker-preset-btn {
    padding: 8px 14px;
    font-size: 13px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #f8f9fa;
    cursor: pointer;
    color: #495057;
    transition: all 0.2s;
    height: 36px;
    line-height: 1;
}

.time-picker-preset-btn:hover {
    background-color: #e9ecef;
    border-color: #007bff;
    color: #007bff;
}

.time-picker-preset-btn.active {
    background-color: #007bff;
    border-color: #007bff;
    color: #fff;
}

.time-picker-custom {
    padding: 12px;
}

.time-picker-custom-title {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 10px;
    font-weight: 600;
}

.time-picker-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 12px;
}

.time-picker-input-group {
    display: flex;
    flex-direction: column;
}

.time-picker-input-group label {
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 4px;
}

.time-picker-input-group input {
    padding: 8px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    width: 100%;
    box-sizing: border-box;
}

.time-picker-input-group input:focus {
    border-color: #007bff;
    outline: none;
}

.time-picker-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.time-picker-actions button {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 4px;
    cursor: pointer;
    height: 36px;
    line-height: 1;
}

.time-picker-apply-btn {
    background-color: #007bff;
    border: 1px solid #007bff;
    color: #fff;
}

.time-picker-apply-btn:hover {
    background-color: #0056b3;
}

.time-picker-cancel-btn {
    background-color: #fff;
    border: 1px solid #dee2e6;
    color: #495057;
}

.time-picker-cancel-btn:hover {
    background-color: #e9ecef;
}

@media (max-width: 1200px) {
    .filter-panel {
        grid-template-columns: 1fr 1fr;
    }
    
    .graphs-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .graphtrees-container {
        flex-direction: column;
    }
    
    .tree-panel {
        width: 100%;
        height: 300px;
        border-right: none;
        border-bottom: 1px solid #dee2e6;
    }
    
    .filter-panel {
        grid-template-columns: 1fr;
    }
}
');

// 构建树形结构HTML
$treeHtml = new CDiv();
$treeHtml->addClass('tree-panel');

// 树头部
$treeHeader = (new CDiv())
    ->addClass('tree-header')
    ->addItem(new CTag('h3', true, LanguageManager::t('Resource Tree')))
    ->addItem(
        (new CDiv())
            ->addClass('tree-controls')
            ->addItem(
                (new CButton('expand-all', LanguageManager::t('Expand All')))
                    ->addClass('btn btn-secondary btn-sm')
                    ->onClick('expandAllGroups()')
            )
            ->addItem(
                (new CButton('collapse-all', LanguageManager::t('Collapse All')))
                    ->addClass('btn btn-secondary btn-sm')
                    ->onClick('collapseAllGroups()')
            )
    );

$treeHtml->addItem($treeHeader);

// 搜索框
$treeSearch = (new CDiv())
    ->addClass('tree-search')
    ->addItem(
        (new CTextBox('tree_search', ''))
            ->setAttribute('placeholder', LanguageManager::t('Search...'))
            ->setAttribute('oninput', 'filterTree(this.value)')
    );

$treeHtml->addItem($treeSearch);

// 树节点
$treeNodes = new CDiv();
$treeNodes->addClass('tree-nodes');
$treeNodes->setAttribute('id', 'tree-nodes');

if (!empty($data['tree_data'])) {
    foreach ($data['tree_data'] as $group) {
        $groupDiv = (new CDiv())->addClass('tree-group');
        
        // 分组头
        $groupHeader = (new CDiv())
            ->addClass('tree-group-header')
            ->setAttribute('onclick', 'toggleGroup(this)')
            ->addItem((new CSpan('▼'))->addClass('tree-group-icon'))
            ->addItem(new CSpan('📂 ' . htmlspecialchars($group['groupname'])));
        
        $groupDiv->addItem($groupHeader);
        
        // 主机列表
        $hostsDiv = (new CDiv())->addClass('tree-hosts expanded');
        
        foreach ($group['hosts'] as $host) {
            $hostDiv = (new CDiv())
                ->addClass('tree-host')
                ->setAttribute('data-hostid', $host['hostid'])
                ->setAttribute('data-hostname', htmlspecialchars($host['name']))
                ->setAttribute('onclick', 'selectHost(' . $host['hostid'] . ', "' . htmlspecialchars($host['name']) . '")')
                ->addItem((new CSpan('🖥️'))->addClass('tree-host-icon'))
                ->addItem(new CSpan(htmlspecialchars($host['name'])));
            
            if ($data['selected_hostid'] == $host['hostid']) {
                $hostDiv->addClass('selected');
            }
            
            $hostsDiv->addItem($hostDiv);
        }
        
        $groupDiv->addItem($hostsDiv);
        $treeNodes->addItem($groupDiv);
    }
}

$treeHtml->addItem($treeNodes);

// 内容面板
$contentPanel = (new CDiv())->addClass('content-panel');

// 内容头部
$contentHeader = (new CDiv())->addClass('content-header');

$contentTitle = (new CDiv())
    ->addClass('content-title')
    ->addItem(LanguageManager::t('Monitoring Graphs'));

$contentHeader->addItem($contentTitle);

// 过滤面板
$filterPanel = (new CDiv())->addClass('filter-panel');

// 标记选择
$tagField = (new CDiv())->addClass('filter-field');
$tagField->addItem(new CLabel(LanguageManager::t('Tags')));
$tagSelect = new CTag('select', true);
$tagSelect->setAttribute('id', 'tag-select');
$tagSelect->setAttribute('onchange', 'onFilterChange()');
$tagSelect->addItem((new CTag('option', true, LanguageManager::t('All Tags')))->setAttribute('value', ''));

if (!empty($data['available_tags'])) {
    foreach ($data['available_tags'] as $tagInfo) {
        $option = new CTag('option', true, htmlspecialchars($tagInfo['tag']));
        $option->setAttribute('value', htmlspecialchars($tagInfo['tag']));
        if ($data['selected_tag'] === $tagInfo['tag']) {
            $option->setAttribute('selected', 'selected');
        }
        $tagSelect->addItem($option);
    }
}

$tagField->addItem($tagSelect);
$filterPanel->addItem($tagField);

// 标记值选择
$tagValueField = (new CDiv())->addClass('filter-field');
$tagValueField->addItem(new CLabel(LanguageManager::t('Tag Value')));
$tagValueSelect = new CTag('select', true);
$tagValueSelect->setAttribute('id', 'tag-value-select');
$tagValueSelect->setAttribute('onchange', 'onTagValueChange()');
$tagValueSelect->addItem((new CTag('option', true, LanguageManager::t('All Values')))->setAttribute('value', ''));
$tagValueField->addItem($tagValueSelect);
$filterPanel->addItem($tagValueField);

// 监控项多选下拉框
$itemsField = (new CDiv())->addClass('filter-field');
$itemsField->addItem(new CLabel(LanguageManager::t('Items')));
$itemsMultiSelect = (new CDiv())
    ->addClass('multi-select-container')
    ->setAttribute('id', 'items-multi-select');

$itemsTrigger = (new CDiv())
    ->addClass('multi-select-trigger')
    ->setAttribute('onclick', 'toggleItemsDropdown()')
    ->addItem((new CSpan(LanguageManager::t('All Items')))->addClass('trigger-text')->setAttribute('id', 'items-trigger-text'))
    ->addItem((new CSpan('▼'))->addClass('trigger-arrow'));

$itemsDropdown = (new CDiv())
    ->addClass('multi-select-dropdown')
    ->setAttribute('id', 'items-dropdown');

// 搜索框
$itemsSearch = (new CDiv())
    ->addClass('multi-select-search')
    ->addItem(
        (new CTextBox('items_search', ''))
            ->setAttribute('placeholder', LanguageManager::t('Search...'))
            ->setAttribute('oninput', 'filterItemsOptions(this.value)')
    );
$itemsDropdown->addItem($itemsSearch);

// 全选/取消全选按钮
$itemsActions = (new CDiv())
    ->addClass('multi-select-actions')
    ->addItem(
        (new CButton('select-all-items', LanguageManager::t('Select All')))
            ->setAttribute('onclick', 'selectAllItems()')
    )
    ->addItem(
        (new CButton('deselect-all-items', LanguageManager::t('Deselect All')))
            ->setAttribute('onclick', 'deselectAllItems()')
    );
$itemsDropdown->addItem($itemsActions);

// 选项容器
$itemsOptions = (new CDiv())
    ->addClass('multi-select-options')
    ->setAttribute('id', 'items-options');
$itemsDropdown->addItem($itemsOptions);

$itemsMultiSelect->addItem($itemsTrigger);
$itemsMultiSelect->addItem($itemsDropdown);
$itemsField->addItem($itemsMultiSelect);
$filterPanel->addItem($itemsField);

// 时间选择器
$timeField = (new CDiv())->addClass('filter-field');
$timeField->addItem(new CLabel(LanguageManager::t('Time Range')));

$timePickerContainer = (new CDiv())
    ->addClass('time-picker-container')
    ->setAttribute('id', 'time-picker-container');

// 时间选择器触发器
$timePickerTrigger = (new CDiv())
    ->addClass('time-picker-trigger')
    ->setAttribute('id', 'time-picker-trigger')
    ->setAttribute('onclick', 'toggleTimePicker()')
    ->addItem((new CSpan(''))->addClass('trigger-text')->setAttribute('id', 'time-picker-text'))
    ->addItem((new CSpan('🕐'))->addClass('trigger-icon'));

$timePickerContainer->addItem($timePickerTrigger);

// 时间选择器下拉框（由JavaScript动态生成内容）
$timePickerDropdown = (new CDiv())
    ->addClass('time-picker-dropdown')
    ->setAttribute('id', 'time-picker-dropdown');

$timePickerContainer->addItem($timePickerDropdown);
$timeField->addItem($timePickerContainer);
$filterPanel->addItem($timeField);

// 自动刷新下拉框
$autoRefreshField = (new CDiv())->addClass('filter-field');
$autoRefreshField->addItem(new CLabel(LanguageManager::t('Auto Refresh')));
$autoRefreshSelect = new CTag('select', true);
$autoRefreshSelect->setAttribute('id', 'auto-refresh-select');
$autoRefreshSelect->setAttribute('onchange', 'onAutoRefreshChange()');

$autoRefreshOptions = [
    '0' => LanguageManager::t('Off'),
    '5' => '5 ' . LanguageManager::t('seconds'),
    '10' => '10 ' . LanguageManager::t('seconds'),
    '20' => '20 ' . LanguageManager::t('seconds'),
    '30' => '30 ' . LanguageManager::t('seconds'),
    '60' => '60 ' . LanguageManager::t('seconds')
];

foreach ($autoRefreshOptions as $value => $label) {
    $option = new CTag('option', true, $label);
    $option->setAttribute('value', $value);
    if ($value === '5') {
        $option->setAttribute('selected', 'selected');
    }
    $autoRefreshSelect->addItem($option);
}

$autoRefreshField->addItem($autoRefreshSelect);
$filterPanel->addItem($autoRefreshField);

$contentHeader->addItem($filterPanel);
$contentPanel->addItem($contentHeader);

// 内容主体
$contentBody = (new CDiv())->addClass('content-body');
$contentBody->setAttribute('id', 'content-body');

if ($data['selected_hostid'] > 0) {
    if (!empty($data['items'])) {
        $graphsContainer = (new CDiv())
            ->addClass('graphs-container')
            ->setAttribute('id', 'graphs-container');
        
        $contentBody->addItem($graphsContainer);
    } else {
        $emptyState = (new CDiv())
            ->addClass('empty-state')
            ->addItem((new CDiv('📊'))->addClass('empty-state-icon'))
            ->addItem((new CDiv(LanguageManager::t('No items found for this host')))->addClass('empty-state-text'));
        
        $contentBody->addItem($emptyState);
    }
} else {
    $emptyState = (new CDiv())
        ->addClass('empty-state')
        ->addItem((new CDiv('🌳'))->addClass('empty-state-icon'))
        ->addItem((new CDiv(LanguageManager::t('Select a host to view graphs')))->addClass('empty-state-text'));
    
    $contentBody->addItem($emptyState);
}

$contentPanel->addItem($contentBody);

// 主容器
$mainContainer = (new CDiv())
    ->addClass('graphtrees-container')
    ->addItem($treeHtml)
    ->addItem($contentPanel);

// JavaScript
$currentHostId = (int)($data['selected_hostid'] ?? 0);
$currentTag = $data['selected_tag'] ?? '';
$currentTagValue = $data['selected_tag_value'] ?? '';
$availableTags = $data['available_tags'] ?? [];
$items = $data['items'] ?? [];

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $mainContainer);

// JavaScript - 直接输出避免HTML转义
$jsVars = [
    'currentHostId' => $currentHostId,
    'currentTag' => $currentTag,
    'currentTagValue' => $currentTagValue,
    'currentTimeFrom' => $currentTimeFrom,
    'currentTimeTo' => $currentTimeTo,
    'availableTags' => $availableTags,
    'items' => $items,
    'i18n' => [
        'allValues' => LanguageManager::t('All Values'),
        'allItems' => LanguageManager::t('All Items'),
        'selectedItems' => LanguageManager::t('selected'),
        'selectAll' => LanguageManager::t('Select All'),
        'deselectAll' => LanguageManager::t('Deselect All'),
        'loading' => LanguageManager::t('Loading...'),
        'failedToLoad' => LanguageManager::t('Failed to load data'),
        'noData' => LanguageManager::t('No data'),
        'noValidData' => LanguageManager::t('No valid data'),
        'zoomIn' => LanguageManager::t('Zoom In'),
        'close' => LanguageManager::t('Close'),
        'quickSelect' => LanguageManager::t('Quick Select'),
        'customRange' => LanguageManager::t('Custom Range'),
        'from' => LanguageManager::t('From'),
        'to' => LanguageManager::t('To'),
        'apply' => LanguageManager::t('Apply'),
        'cancel' => LanguageManager::t('Cancel'),
        'last10Minutes' => LanguageManager::t('Last 10 Minutes'),
        'last30Minutes' => LanguageManager::t('Last 30 Minutes'),
        'lastHour' => LanguageManager::t('Last Hour'),
        'last3Hours' => LanguageManager::t('Last 3 Hours'),
        'last12Hours' => LanguageManager::t('Last 12 Hours'),
        'last24Hours' => LanguageManager::t('Last 24 Hours')
    ]
];
?>
<script>
var graphTreesConfig = <?php echo json_encode($jsVars, JSON_UNESCAPED_UNICODE); ?>;
var currentHostId = graphTreesConfig.currentHostId;
var currentTag = graphTreesConfig.currentTag;
var currentTagValue = graphTreesConfig.currentTagValue;
var currentTimeFrom = graphTreesConfig.currentTimeFrom;
var currentTimeTo = graphTreesConfig.currentTimeTo;
var availableTags = graphTreesConfig.availableTags;
var items = graphTreesConfig.items;
var selectedItemIds = []; // 用户选择的监控项ID列表
var allItems = []; // 所有可用的监控项

// 初始化监控项多选下拉框
function initItemsMultiSelect() {
    allItems = items.slice(); // 复制所有监控项
    selectedItemIds = allItems.map(function(item) { return item.itemid; }); // 默认全选
    renderItemsOptions();
    updateItemsTriggerText();
}

// 渲染监控项选项
function renderItemsOptions(filter) {
    var container = document.getElementById("items-options");
    if (!container) return;
    
    container.innerHTML = "";
    
    var filteredItems = allItems;
    if (filter) {
        var filterLower = filter.toLowerCase();
        filteredItems = allItems.filter(function(item) {
            return item.name.toLowerCase().indexOf(filterLower) !== -1;
        });
    }
    
    filteredItems.forEach(function(item) {
        var optionDiv = document.createElement("div");
        optionDiv.className = "multi-select-option";
        if (selectedItemIds.indexOf(item.itemid) !== -1) {
            optionDiv.className += " selected";
        }
        optionDiv.setAttribute("data-itemid", item.itemid);
        
        var checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = selectedItemIds.indexOf(item.itemid) !== -1;
        checkbox.id = "item-checkbox-" + item.itemid;
        checkbox.onchange = function(e) {
            e.stopPropagation();
            toggleItemSelection(item.itemid);
        };
        
        var label = document.createElement("label");
        label.htmlFor = "item-checkbox-" + item.itemid;
        label.textContent = item.name;
        
        optionDiv.appendChild(checkbox);
        optionDiv.appendChild(label);
        
        optionDiv.onclick = function(e) {
            e.stopPropagation();
            if (e.target.tagName !== "INPUT") {
                checkbox.checked = !checkbox.checked;
                toggleItemSelection(item.itemid);
            }
        };
        
        container.appendChild(optionDiv);
    });
}

// 切换监控项选择状态
function toggleItemSelection(itemid) {
    var index = selectedItemIds.indexOf(itemid);
    if (index === -1) {
        selectedItemIds.push(itemid);
    } else {
        selectedItemIds.splice(index, 1);
    }
    renderItemsOptions(document.querySelector("#items-dropdown input[type=text]")?.value || "");
    updateItemsTriggerText();
    renderGraphs();
}

// 更新触发器显示文本
function updateItemsTriggerText() {
    var triggerText = document.getElementById("items-trigger-text");
    if (!triggerText) return;
    
    if (selectedItemIds.length === 0) {
        triggerText.textContent = graphTreesConfig.i18n.allItems;
    } else if (selectedItemIds.length === allItems.length) {
        triggerText.textContent = graphTreesConfig.i18n.allItems;
    } else {
        triggerText.textContent = selectedItemIds.length + " " + graphTreesConfig.i18n.selectedItems;
    }
}

// 切换下拉框显示
function toggleItemsDropdown() {
    var dropdown = document.getElementById("items-dropdown");
    if (dropdown) {
        dropdown.classList.toggle("show");
    }
}

// 过滤监控项选项
function filterItemsOptions(value) {
    renderItemsOptions(value);
}

// 全选
function selectAllItems() {
    selectedItemIds = allItems.map(function(item) { return item.itemid; });
    renderItemsOptions(document.querySelector("#items-dropdown input[type=text]")?.value || "");
    updateItemsTriggerText();
    renderGraphs();
}

// 取消全选
function deselectAllItems() {
    selectedItemIds = [];
    renderItemsOptions(document.querySelector("#items-dropdown input[type=text]")?.value || "");
    updateItemsTriggerText();
    renderGraphs();
}

// 点击页面其他区域关闭下拉框
document.addEventListener("click", function(e) {
    // 关闭监控项下拉框
    var itemsContainer = document.getElementById("items-multi-select");
    if (itemsContainer && !itemsContainer.contains(e.target)) {
        var itemsDropdown = document.getElementById("items-dropdown");
        if (itemsDropdown) {
            itemsDropdown.classList.remove("show");
        }
    }
    
    // 关闭时间选择器下拉框
    var timeContainer = document.getElementById("time-picker-container");
    if (timeContainer && !timeContainer.contains(e.target)) {
        closeTimePicker();
    }
});

function toggleGroup(element) {
    var icon = element.querySelector(".tree-group-icon");
    var hostsDiv = element.parentElement.querySelector(".tree-hosts");
    
    if (hostsDiv.classList.contains("expanded")) {
        hostsDiv.classList.remove("expanded");
        icon.classList.add("collapsed");
    } else {
        hostsDiv.classList.add("expanded");
        icon.classList.remove("collapsed");
    }
}

function expandAllGroups() {
    document.querySelectorAll(".tree-hosts").forEach(function(div) {
        div.classList.add("expanded");
    });
    document.querySelectorAll(".tree-group-icon").forEach(function(icon) {
        icon.classList.remove("collapsed");
    });
}

function collapseAllGroups() {
    document.querySelectorAll(".tree-hosts").forEach(function(div) {
        div.classList.remove("expanded");
    });
    document.querySelectorAll(".tree-group-icon").forEach(function(icon) {
        icon.classList.add("collapsed");
    });
}

function selectHost(hostid, hostname) {
    document.querySelectorAll(".tree-host").forEach(function(div) {
        div.classList.remove("selected");
    });
    
    event.target.closest(".tree-host").classList.add("selected");
    
    var url = "?action=graphtrees&hostid=" + hostid;
    if (currentTag) {
        url += "&tag=" + encodeURIComponent(currentTag);
    }
    if (currentTagValue) {
        url += "&tag_value=" + encodeURIComponent(currentTagValue);
    }
    url += "&time_from=" + currentTimeFrom + "&time_to=" + currentTimeTo;
    
    window.location.href = url;
}

function filterTree(searchText) {
    var lowerSearch = searchText.toLowerCase();
    
    document.querySelectorAll(".tree-group").forEach(function(group) {
        var groupVisible = false;
        var hosts = group.querySelectorAll(".tree-host");
        
        hosts.forEach(function(host) {
            var hostname = host.getAttribute("data-hostname").toLowerCase();
            if (hostname.indexOf(lowerSearch) !== -1) {
                host.style.display = "flex";
                groupVisible = true;
            } else {
                host.style.display = "none";
            }
        });
        
        if (groupVisible || searchText === "") {
            group.style.display = "block";
        } else {
            group.style.display = "none";
        }
    });
}

function onFilterChange() {
    var tag = document.getElementById("tag-select").value;
    currentTag = tag;
    
    // 更新标记值下拉列表
    updateTagValueSelect(tag);
    
    // 重置标记值
    currentTagValue = "";
    
    // 自动刷新页面应用过滤
    if (currentHostId > 0) {
        refreshGraphs();
    }
}

function onTagValueChange() {
    var tagValue = document.getElementById("tag-value-select").value;
    currentTagValue = tagValue;
    
    // 选择标记值后刷新
    if (currentHostId > 0) {
        refreshGraphs();
    }
}

function applyFilter() {
    // 手动点击刷新按钮时应用过滤
    if (currentHostId > 0) {
        refreshGraphs();
    }
}

function updateTagValueSelect(tag) {
    var select = document.getElementById("tag-value-select");
    select.innerHTML = "";
    
    var allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = graphTreesConfig.i18n.allValues;
    select.appendChild(allOption);
    
    if (tag) {
        availableTags.forEach(function(tagInfo) {
            if (tagInfo.tag === tag) {
                tagInfo.values.forEach(function(value) {
                    var option = document.createElement("option");
                    option.value = value;
                    option.textContent = value;
                    select.appendChild(option);
                });
            }
        });
    }
}

// 时间选择器相关变量和函数
var timePresets = [
    { value: 600, label: 'last10Minutes' },
    { value: 1800, label: 'last30Minutes' },
    { value: 3600, label: 'lastHour' },
    { value: 10800, label: 'last3Hours' },
    { value: 43200, label: 'last12Hours' },
    { value: 86400, label: 'last24Hours' }
];
var selectedPreset = 3600; // 默认选中1小时

// 初始化时间选择器
function initTimePicker() {
    var dropdown = document.getElementById("time-picker-dropdown");
    if (!dropdown) return;
    
    // 构建下拉框内容
    var html = '';
    
    // 快速选择区域
    html += '<div class="time-picker-presets">';
    html += '<div class="time-picker-presets-title">' + graphTreesConfig.i18n.quickSelect + '</div>';
    html += '<div class="time-picker-preset-buttons">';
    timePresets.forEach(function(preset) {
        var activeClass = (currentTimeTo - currentTimeFrom === preset.value) ? ' active' : '';
        html += '<button class="time-picker-preset-btn' + activeClass + '" data-value="' + preset.value + '" onclick="selectTimePreset(' + preset.value + ')">' + graphTreesConfig.i18n[preset.label] + '</button>';
    });
    html += '</div>';
    html += '</div>';
    
    // 自定义时间区域
    html += '<div class="time-picker-custom">';
    html += '<div class="time-picker-custom-title">' + graphTreesConfig.i18n.customRange + '</div>';
    html += '<div class="time-picker-inputs">';
    html += '<div class="time-picker-input-group">';
    html += '<label>' + graphTreesConfig.i18n.from + '</label>';
    html += '<input type="datetime-local" id="time-picker-from" value="' + formatDateTimeLocal(currentTimeFrom * 1000) + '">';
    html += '</div>';
    html += '<div class="time-picker-input-group">';
    html += '<label>' + graphTreesConfig.i18n.to + '</label>';
    html += '<input type="datetime-local" id="time-picker-to" value="' + formatDateTimeLocal(currentTimeTo * 1000) + '">';
    html += '</div>';
    html += '</div>';
    html += '<div class="time-picker-actions">';
    html += '<button class="time-picker-cancel-btn" onclick="closeTimePicker()">' + graphTreesConfig.i18n.cancel + '</button>';
    html += '<button class="time-picker-apply-btn" onclick="applyCustomTimeRange()">' + graphTreesConfig.i18n.apply + '</button>';
    html += '</div>';
    html += '</div>';
    
    dropdown.innerHTML = html;
    
    // 为datetime-local输入框绑定点击事件
    bindDateTimePickerEvents();
    
    // 更新显示文本
    updateTimePickerText();
}

// 格式化日期为datetime-local格式
function formatDateTimeLocal(timestamp) {
    var date = new Date(timestamp);
    var year = date.getFullYear();
    var month = ("0" + (date.getMonth() + 1)).slice(-2);
    var day = ("0" + date.getDate()).slice(-2);
    var hours = ("0" + date.getHours()).slice(-2);
    var minutes = ("0" + date.getMinutes()).slice(-2);
    return year + "-" + month + "-" + day + "T" + hours + ":" + minutes;
}

// 格式化时间显示
function formatTimeDisplay(timestamp) {
    var date = new Date(timestamp * 1000);
    var month = ("0" + (date.getMonth() + 1)).slice(-2);
    var day = ("0" + date.getDate()).slice(-2);
    var hours = ("0" + date.getHours()).slice(-2);
    var minutes = ("0" + date.getMinutes()).slice(-2);
    return month + "-" + day + " " + hours + ":" + minutes;
}

// 更新时间选择器显示文本
function updateTimePickerText() {
    var textEl = document.getElementById("time-picker-text");
    if (!textEl) return;
    
    var timeDiff = currentTimeTo - currentTimeFrom;
    var presetLabel = null;
    
    // 检查是否匹配预设选项
    timePresets.forEach(function(preset) {
        if (Math.abs(timeDiff - preset.value) < 60) {
            presetLabel = graphTreesConfig.i18n[preset.label];
        }
    });
    
    if (presetLabel) {
        textEl.textContent = presetLabel;
    } else {
        // 显示自定义时间范围
        textEl.textContent = formatTimeDisplay(currentTimeFrom) + " ~ " + formatTimeDisplay(currentTimeTo);
    }
}

// 切换时间选择器下拉框
function toggleTimePicker() {
    var dropdown = document.getElementById("time-picker-dropdown");
    if (dropdown) {
        var isShowing = dropdown.classList.contains("show");
        // 先关闭其他下拉框
        closeAllDropdowns();
        if (!isShowing) {
            dropdown.classList.add("show");
            // 更新输入框的值
            var fromInput = document.getElementById("time-picker-from");
            var toInput = document.getElementById("time-picker-to");
            if (fromInput) fromInput.value = formatDateTimeLocal(currentTimeFrom * 1000);
            if (toInput) toInput.value = formatDateTimeLocal(currentTimeTo * 1000);
            // 更新预设按钮状态
            updatePresetButtonsState();
            // 绑定datetime-local点击事件
            bindDateTimePickerEvents();
        }
    }
}

// 绑定datetime-local输入框点击事件
function bindDateTimePickerEvents() {
    var fromInput = document.getElementById("time-picker-from");
    var toInput = document.getElementById("time-picker-to");
    if (fromInput && !fromInput._pickerBound) {
        fromInput.addEventListener("click", function() {
            if (this.showPicker) this.showPicker();
        });
        fromInput._pickerBound = true;
    }
    if (toInput && !toInput._pickerBound) {
        toInput.addEventListener("click", function() {
            if (this.showPicker) this.showPicker();
        });
        toInput._pickerBound = true;
    }
}

// 关闭时间选择器
function closeTimePicker() {
    var dropdown = document.getElementById("time-picker-dropdown");
    if (dropdown) {
        dropdown.classList.remove("show");
    }
}

// 关闭所有下拉框
function closeAllDropdowns() {
    var itemsDropdown = document.getElementById("items-dropdown");
    if (itemsDropdown) itemsDropdown.classList.remove("show");
    closeTimePicker();
}

// 更新预设按钮状态
function updatePresetButtonsState() {
    var timeDiff = currentTimeTo - currentTimeFrom;
    document.querySelectorAll(".time-picker-preset-btn").forEach(function(btn) {
        var value = parseInt(btn.getAttribute("data-value"));
        if (Math.abs(timeDiff - value) < 60) {
            btn.classList.add("active");
        } else {
            btn.classList.remove("active");
        }
    });
}

// 选择预设时间范围
function selectTimePreset(seconds) {
    var now = Math.floor(Date.now() / 1000);
    currentTimeTo = now;
    currentTimeFrom = now - seconds;
    selectedPreset = seconds;
    
    // 更新显示
    updateTimePickerText();
    updatePresetButtonsState();
    
    // 更新输入框
    var fromInput = document.getElementById("time-picker-from");
    var toInput = document.getElementById("time-picker-to");
    if (fromInput) fromInput.value = formatDateTimeLocal(currentTimeFrom * 1000);
    if (toInput) toInput.value = formatDateTimeLocal(currentTimeTo * 1000);
    
    // 关闭下拉框并刷新图表
    closeTimePicker();
    if (currentHostId > 0 && items.length > 0) {
        renderGraphs();
    }
}

// 应用自定义时间范围
function applyCustomTimeRange() {
    var fromInput = document.getElementById("time-picker-from");
    var toInput = document.getElementById("time-picker-to");
    
    if (!fromInput || !toInput) return;
    
    var fromTime = new Date(fromInput.value).getTime() / 1000;
    var toTime = new Date(toInput.value).getTime() / 1000;
    
    if (isNaN(fromTime) || isNaN(toTime)) {
        alert("请输入有效的时间");
        return;
    }
    
    if (fromTime >= toTime) {
        alert("开始时间必须小于结束时间");
        return;
    }
    
    currentTimeFrom = Math.floor(fromTime);
    currentTimeTo = Math.floor(toTime);
    selectedPreset = null;
    
    // 更新显示
    updateTimePickerText();
    updatePresetButtonsState();
    
    // 关闭下拉框并刷新图表
    closeTimePicker();
    if (currentHostId > 0 && items.length > 0) {
        renderGraphs();
    }
}

function refreshGraphs() {
    if (currentHostId === 0) {
        return;
    }
    
    var url = "?action=graphtrees&hostid=" + currentHostId;
    url += "&tag=" + encodeURIComponent(currentTag);
    
    var tagValue = document.getElementById("tag-value-select").value;
    if (tagValue) {
        url += "&tag_value=" + encodeURIComponent(tagValue);
    }
    
    url += "&time_from=" + currentTimeFrom + "&time_to=" + currentTimeTo;
    
    window.location.href = url;
}

document.addEventListener("DOMContentLoaded", function() {
    if (currentTag) {
        updateTagValueSelect(currentTag);
        if (currentTagValue) {
            document.getElementById("tag-value-select").value = currentTagValue;
        }
    }
    
    // 初始化监控项多选下拉框
    initItemsMultiSelect();
    
    // 初始化时间选择器
    initTimePicker();
    
    if (items.length > 0 && currentHostId > 0) {
        renderGraphs();
    }
});

// 自动刷新功能
var autoRefreshInterval = null;
var autoRefreshSeconds = 5;

function onAutoRefreshChange() {
    var select = document.getElementById("auto-refresh-select");
    var seconds = parseInt(select.value);
    
    if (seconds === 0) {
        // 停止自动刷新
        stopAutoRefresh();
    } else {
        // 启动或更新自动刷新间隔
        autoRefreshSeconds = seconds;
        startAutoRefresh();
    }
}

function startAutoRefresh() {
    stopAutoRefresh(); // 先清除旧的
    autoRefreshInterval = setInterval(function() {
        if (currentHostId > 0 && items.length > 0) {
            // 如果选择了预设时间范围，更新到当前时间
            if (selectedPreset) {
                var now = Math.floor(Date.now() / 1000);
                currentTimeTo = now;
                currentTimeFrom = now - selectedPreset;
                updateTimePickerText();
            }
            renderGraphs();
        }
    }, autoRefreshSeconds * 1000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// 全局图表管理器 - 用于同步所有图表的tooltip显示
var chartManager = {
    charts: [],
    register: function(chart) {
        this.charts.push(chart);
    },
    clear: function() {
        this.charts = [];
    },
    // 使用比例（0-1）来同步，而不是时间戳
    broadcastRatio: function(ratio) {
        this.charts.forEach(function(chart) {
            chart.showTooltipAtRatio(ratio);
        });
    },
    hideAll: function() {
        this.charts.forEach(function(chart) {
            chart.hideTooltip();
        });
    }
};

// 放大图表弹窗
function openGraphModal(graphData) {
    // 创建遮罩层
    var overlay = document.createElement("div");
    overlay.className = "graph-modal-overlay";
    overlay.id = "graph-modal-overlay";
    
    // 创建弹窗
    var modal = document.createElement("div");
    modal.className = "graph-modal";
    
    // 弹窗头部
    var header = document.createElement("div");
    header.className = "graph-modal-header";
    
    var title = document.createElement("h3");
    title.className = "graph-modal-title";
    title.textContent = graphData.name + (graphData.units ? " (" + graphData.units + ")" : "");
    header.appendChild(title);
    
    var closeBtn = document.createElement("button");
    closeBtn.className = "graph-modal-close";
    closeBtn.innerHTML = "×";
    closeBtn.title = graphTreesConfig.i18n.close || "关闭";
    closeBtn.onclick = closeGraphModal;
    header.appendChild(closeBtn);
    
    modal.appendChild(header);
    
    // 弹窗内容
    var body = document.createElement("div");
    body.className = "graph-modal-body";
    
    var canvas = document.createElement("div");
    canvas.className = "graph-modal-canvas";
    canvas.id = "modal-chart-container";
    body.appendChild(canvas);
    
    modal.appendChild(body);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // 禁止背景滚动
    document.body.style.overflow = "hidden";
    
    // 点击遮罩层关闭
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            closeGraphModal();
        }
    };
    
    // 按ESC键关闭
    document.addEventListener("keydown", handleModalEscape);
    
    // 绘制放大的图表
    setTimeout(function() {
        if (graphData.data && graphData.data.length > 0) {
            drawLineChart(canvas, graphData.data, graphData.units, currentTimeFrom * 1000, currentTimeTo * 1000, true);
        } else {
            canvas.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">' + graphTreesConfig.i18n.noData + '</div>';
        }
    }, 100);
}

function handleModalEscape(e) {
    if (e.key === "Escape") {
        closeGraphModal();
    }
}

function closeGraphModal() {
    var overlay = document.getElementById("graph-modal-overlay");
    if (overlay) {
        overlay.remove();
        document.body.style.overflow = "";
        document.removeEventListener("keydown", handleModalEscape);
    }
}

function renderGraphs() {
    var container = document.getElementById("graphs-container");
    if (!container) return;
    
    // 清除之前注册的图表
    chartManager.clear();
    
    // 获取要显示的监控项ID（根据用户选择过滤）
    var itemIdsToShow = selectedItemIds;
    
    if (itemIdsToShow.length === 0) {
        container.innerHTML = '<div class="empty-state"><div>' + graphTreesConfig.i18n.noData + '</div></div>';
        return;
    }
    
    container.innerHTML = '<div class="loading-state"><div class="spinner"></div><div>' + graphTreesConfig.i18n.loading + '</div></div>';
    
    fetch("?action=graphtrees.data&itemids=" + JSON.stringify(itemIdsToShow) + "&time_from=" + currentTimeFrom + "&time_to=" + currentTimeTo)
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success && result.data) {
                container.innerHTML = "";
                
                result.data.forEach(function(graphData) {
                    var card = document.createElement("div");
                    card.className = "graph-card";
                    
                    // 创建图表头部容器
                    var header = document.createElement("div");
                    header.className = "graph-header";
                    
                    var title = document.createElement("div");
                    title.className = "graph-title";
                    title.textContent = graphData.name + (graphData.units ? " (" + graphData.units + ")" : "");
                    header.appendChild(title);
                    
                    // 添加放大按钮
                    var zoomBtn = document.createElement("button");
                    zoomBtn.className = "graph-zoom-btn";
                    zoomBtn.innerHTML = "⛶";
                    zoomBtn.title = graphTreesConfig.i18n.zoomIn || "放大";
                    zoomBtn.onclick = (function(gData) {
                        return function(e) {
                            e.stopPropagation();
                            openGraphModal(gData);
                        };
                    })(graphData);
                    header.appendChild(zoomBtn);
                    
                    card.appendChild(header);
                    
                    // 创建图表容器
                    var chartContainer = document.createElement("div");
                    chartContainer.className = "graph-canvas";
                    chartContainer.style.position = "relative";
                    card.appendChild(chartContainer);
                    
                    container.appendChild(card);
                    
                    // 绘制SVG折线图，传入用户选择的时间范围
                    if (graphData.data && graphData.data.length > 0) {
                        drawLineChart(chartContainer, graphData.data, graphData.units, currentTimeFrom * 1000, currentTimeTo * 1000);
                    } else {
                        chartContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">' + graphTreesConfig.i18n.noData + '</div>';
                    }
                });
            }
        })
        .catch(function(error) {
            console.error("Error loading graph data:", error);
            container.innerHTML = '<div class="empty-state"><div>' + graphTreesConfig.i18n.failedToLoad + '</div></div>';
        });
}

function drawLineChart(container, data, units, requestedTimeFrom, requestedTimeTo, isModal) {
    if (!data || data.length === 0) return;
    
    var width = container.offsetWidth || 500;
    var height = isModal ? (container.offsetHeight || 600) : 280;
    var padding = { top: 20, right: 60, bottom: 40, left: 70 };
    var chartWidth = width - padding.left - padding.right;
    var chartHeight = height - padding.top - padding.bottom;
    
    // 提取数值，过滤掉无效数据
    var validData = [];
    data.forEach(function(d) {
        var val = parseFloat(d.value);
        var time = parseInt(d.clock) * 1000;
        if (!isNaN(val) && !isNaN(time) && isFinite(val)) {
            validData.push({ value: val, time: time });
        }
    });
    
    if (validData.length === 0) {
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">' + graphTreesConfig.i18n.noValidData + '</div>';
        return;
    }
    
    var values = validData.map(function(d) { return d.value; });
    var times = validData.map(function(d) { return d.time; });
    
    var minVal = Math.min.apply(null, values);
    var maxVal = Math.max.apply(null, values);
    
    // 使用用户请求的时间范围作为X轴范围
    var minTime = requestedTimeFrom || Math.min.apply(null, times);
    var maxTime = requestedTimeTo || Math.max.apply(null, times);
    
    // 确保有范围（避免除以0）
    var valRange = maxVal - minVal;
    if (valRange === 0 || !isFinite(valRange)) {
        // 如果所有值相同，创建一个假范围
        var centerVal = minVal || 0;
        minVal = centerVal - 1;
        maxVal = centerVal + 1;
        valRange = 2;
    }
    
    var timeRange = maxTime - minTime;
    if (timeRange === 0 || !isFinite(timeRange)) {
        timeRange = 3600000; // 默认1小时
    }
    
    // 创建SVG
    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("width", width);
    svg.setAttribute("height", height);
    svg.style.display = "block";
    
    // 背景
    var bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    bg.setAttribute("width", width);
    bg.setAttribute("height", height);
    bg.setAttribute("fill", "#fafafa");
    svg.appendChild(bg);
    
    // 绘制网格线
    var gridGroup = document.createElementNS("http://www.w3.org/2000/svg", "g");
    for (var i = 0; i <= 5; i++) {
        var y = padding.top + (chartHeight / 5) * i;
        var line = document.createElementNS("http://www.w3.org/2000/svg", "line");
        line.setAttribute("x1", padding.left);
        line.setAttribute("y1", y);
        line.setAttribute("x2", width - padding.right);
        line.setAttribute("y2", y);
        line.setAttribute("stroke", "#e0e0e0");
        line.setAttribute("stroke-width", "1");
        gridGroup.appendChild(line);
        
        // Y轴标签
        var val = maxVal - (maxVal - minVal) * (i / 5);
        var label = document.createElementNS("http://www.w3.org/2000/svg", "text");
        label.setAttribute("x", padding.left - 10);
        label.setAttribute("y", y + 4);
        label.setAttribute("text-anchor", "end");
        label.setAttribute("font-size", "11");
        label.setAttribute("fill", "#666");
        label.textContent = formatValue(val, units);
        gridGroup.appendChild(label);
    }
    svg.appendChild(gridGroup);
    
    // 绘制折线
    var pathData = "";
    var areaData = "";
    validData.forEach(function(d, i) {
        var x = padding.left + (chartWidth * (times[i] - minTime) / timeRange);
        var y = padding.top + chartHeight - (chartHeight * (values[i] - minVal) / valRange);
        
        // 确保坐标是有效数字
        x = isFinite(x) ? x : padding.left;
        y = isFinite(y) ? y : padding.top + chartHeight / 2;
        
        if (i === 0) {
            pathData += "M " + x + " " + y;
            areaData += "M " + x + " " + (padding.top + chartHeight) + " L " + x + " " + y;
        } else {
            pathData += " L " + x + " " + y;
            areaData += " L " + x + " " + y;
        }
        
        if (i === validData.length - 1) {
            areaData += " L " + x + " " + (padding.top + chartHeight) + " Z";
        }
    });
    
    // 填充区域
    var area = document.createElementNS("http://www.w3.org/2000/svg", "path");
    area.setAttribute("d", areaData);
    area.setAttribute("fill", "rgba(0, 123, 255, 0.1)");
    svg.appendChild(area);
    
    // 折线
    var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute("d", pathData);
    path.setAttribute("stroke", "#007bff");
    path.setAttribute("stroke-width", "2");
    path.setAttribute("fill", "none");
    svg.appendChild(path);
    
    // X轴时间标签
    var timeLabels = 5;
    for (var j = 0; j <= timeLabels; j++) {
        var t = minTime + (maxTime - minTime) * (j / timeLabels);
        var x = padding.left + chartWidth * (j / timeLabels);
        var timeLabel = document.createElementNS("http://www.w3.org/2000/svg", "text");
        timeLabel.setAttribute("x", x);
        timeLabel.setAttribute("y", height - 10);
        timeLabel.setAttribute("text-anchor", "middle");
        timeLabel.setAttribute("font-size", "10");
        timeLabel.setAttribute("fill", "#666");
        timeLabel.textContent = formatTime(t);
        svg.appendChild(timeLabel);
    }
    
    // 坐标轴
    var xAxis = document.createElementNS("http://www.w3.org/2000/svg", "line");
    xAxis.setAttribute("x1", padding.left);
    xAxis.setAttribute("y1", padding.top + chartHeight);
    xAxis.setAttribute("x2", width - padding.right);
    xAxis.setAttribute("y2", padding.top + chartHeight);
    xAxis.setAttribute("stroke", "#ccc");
    xAxis.setAttribute("stroke-width", "1");
    svg.appendChild(xAxis);
    
    var yAxis = document.createElementNS("http://www.w3.org/2000/svg", "line");
    yAxis.setAttribute("x1", padding.left);
    yAxis.setAttribute("y1", padding.top);
    yAxis.setAttribute("x2", padding.left);
    yAxis.setAttribute("y2", padding.top + chartHeight);
    yAxis.setAttribute("stroke", "#ccc");
    yAxis.setAttribute("stroke-width", "1");
    svg.appendChild(yAxis);
    
    // 创建tooltip元素
    var tooltip = document.createElement("div");
    tooltip.className = "chart-tooltip";
    tooltip.style.cssText = "position:absolute;display:none;background:rgba(0,0,0,0.8);color:#fff;padding:8px 12px;border-radius:4px;font-size:12px;pointer-events:none;z-index:1000;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.2);";
    container.appendChild(tooltip);
    
    // 创建垂直指示线
    var verticalLine = document.createElementNS("http://www.w3.org/2000/svg", "line");
    verticalLine.setAttribute("stroke", "#007bff");
    verticalLine.setAttribute("stroke-width", "1");
    verticalLine.setAttribute("stroke-dasharray", "4,4");
    verticalLine.style.display = "none";
    svg.appendChild(verticalLine);
    
    // 创建数据点指示圆
    var dataPoint = document.createElementNS("http://www.w3.org/2000/svg", "circle");
    dataPoint.setAttribute("r", "5");
    dataPoint.setAttribute("fill", "#007bff");
    dataPoint.setAttribute("stroke", "#fff");
    dataPoint.setAttribute("stroke-width", "2");
    dataPoint.style.display = "none";
    svg.appendChild(dataPoint);
    
    // 创建图表对象用于同步
    var chartObj = {
        // 根据比例（0-1）显示tooltip
        showTooltipAtRatio: function(ratio) {
            // 确保比例在有效范围内
            ratio = Math.max(0, Math.min(1, ratio));
            
            // 计算当前图表对应该比例的时间
            var timeAtRatio = minTime + ratio * timeRange;
            
            // 找到最近的数据点
            var closestIndex = 0;
            var closestDist = Infinity;
            validData.forEach(function(d, i) {
                var dist = Math.abs(d.time - timeAtRatio);
                if (dist < closestDist) {
                    closestDist = dist;
                    closestIndex = i;
                }
            });
            
            var closestData = validData[closestIndex];
            
            // 计算垂直线位置（使用比例，保持所有图表垂直线对齐）
            var lineX = padding.left + ratio * chartWidth;
            
            // 计算数据点位置
            var pointX = padding.left + (chartWidth * (closestData.time - minTime) / timeRange);
            var pointY = padding.top + chartHeight - (chartHeight * (closestData.value - minVal) / valRange);
            
            // 确保坐标有效
            lineX = isFinite(lineX) ? lineX : padding.left;
            pointX = isFinite(pointX) ? pointX : padding.left;
            pointY = isFinite(pointY) ? pointY : padding.top + chartHeight / 2;
            
            // 更新垂直线（使用比例位置，保持对齐）
            verticalLine.setAttribute("x1", lineX);
            verticalLine.setAttribute("y1", padding.top);
            verticalLine.setAttribute("x2", lineX);
            verticalLine.setAttribute("y2", padding.top + chartHeight);
            verticalLine.style.display = "block";
            
            // 更新数据点（使用实际数据位置）
            dataPoint.setAttribute("cx", pointX);
            dataPoint.setAttribute("cy", pointY);
            dataPoint.style.display = "block";
            
            // 更新tooltip
            var timeStr = formatTime(closestData.time);
            var valueStr = formatValue(closestData.value, units);
            tooltip.innerHTML = "<div style='margin-bottom:4px;color:#aaa;'>" + timeStr + "</div><div style='font-weight:600;'>" + valueStr + "</div>";
            tooltip.style.display = "block";
            
            // 计算tooltip位置（避免超出容器）
            var tooltipX = lineX + 15;
            var tooltipY = pointY - 40;
            
            if (tooltipX + tooltip.offsetWidth > width) {
                tooltipX = lineX - tooltip.offsetWidth - 15;
            }
            if (tooltipY < 0) {
                tooltipY = pointY + 15;
            }
            
            tooltip.style.left = tooltipX + "px";
            tooltip.style.top = tooltipY + "px";
        },
        hideTooltip: function() {
            tooltip.style.display = "none";
            verticalLine.style.display = "none";
            dataPoint.style.display = "none";
        }
    };
    
    // 注册到全局图表管理器
    chartManager.register(chartObj);
    
    // 创建交互层
    var interactiveRect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    interactiveRect.setAttribute("x", padding.left);
    interactiveRect.setAttribute("y", padding.top);
    interactiveRect.setAttribute("width", chartWidth);
    interactiveRect.setAttribute("height", chartHeight);
    interactiveRect.setAttribute("fill", "transparent");
    interactiveRect.style.cursor = "crosshair";
    
    interactiveRect.addEventListener("mousemove", function(e) {
        var rect = svg.getBoundingClientRect();
        var mouseX = e.clientX - rect.left;
        
        // 计算鼠标位置对应的比例（0-1）
        var ratio = (mouseX - padding.left) / chartWidth;
        
        // 广播比例到所有图表
        chartManager.broadcastRatio(ratio);
    });
    
    interactiveRect.addEventListener("mouseleave", function() {
        // 隐藏所有图表的tooltip
        chartManager.hideAll();
    });
    
    svg.appendChild(interactiveRect);
    
    container.appendChild(svg);
}

function formatValue(val, units) {
    if (Math.abs(val) >= 1000000000) {
        return (val / 1000000000).toFixed(2) + "G" + (units || "");
    } else if (Math.abs(val) >= 1000000) {
        return (val / 1000000).toFixed(2) + "M" + (units || "");
    } else if (Math.abs(val) >= 1000) {
        return (val / 1000).toFixed(2) + "K" + (units || "");
    } else if (Math.abs(val) < 0.01 && val !== 0) {
        return val.toExponential(2) + (units || "");
    } else {
        return val.toFixed(2) + (units || "");
    }
}

function formatTime(timestamp) {
    var date = new Date(timestamp);
    var hours = ("0" + date.getHours()).slice(-2);
    var minutes = ("0" + date.getMinutes()).slice(-2);
    var month = ("0" + (date.getMonth() + 1)).slice(-2);
    var day = ("0" + date.getDate()).slice(-2);
    return month + "-" + day + " " + hours + ":" + minutes;
}
</script>
