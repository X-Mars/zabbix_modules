<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;
use Modules\ZabbixGraphTrees\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('Graph Trees');

// CSS样式
$styleTag = new CTag('style', true, '
.graphtrees-container {
    display: flex;
    height: calc(100vh - 120px);
    gap: 0;
    margin: 0;
    padding: 0;
}

/* ===== 左侧树形面板 ===== */
.tree-panel {
    width: 280px;
    min-width: 280px;
    border-right: 1px solid #dee2e6;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.tree-header {
    padding: 12px 15px;
    border-bottom: 2px solid #007bff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.tree-header h3 {
    margin: 0;
    font-size: 15px;
    color: #495057;
}

.tree-controls {
    display: flex;
    gap: 4px;
}

.tree-controls button {
    font-size: 11px;
    padding: 4px 8px;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    background: #f8f9fa;
    cursor: pointer;
    color: #495057;
}

.tree-controls button:hover {
    background: #e9ecef;
}

.tree-search {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}

.tree-search input {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    box-sizing: border-box;
}

.tree-nodes {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

/* 第一级：主机分组 */
.tree-group {
    margin-bottom: 4px;
}

.tree-group-header {
    padding: 7px 10px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
    user-select: none;
}

.tree-group-header:hover {
    background: #e9ecef;
}

.tree-group-icon {
    font-size: 10px;
    transition: transform 0.2s;
    display: inline-block;
}

.tree-group-icon.collapsed {
    transform: rotate(-90deg);
}

.tree-hosts {
    display: none;
    margin-left: 12px;
    padding-top: 2px;
}

.tree-hosts.expanded {
    display: block;
}

/* 第二级：主机 */
.tree-host {
    padding: 5px 10px;
    margin: 1px 0;
    cursor: pointer;
    border-radius: 3px;
    font-size: 13px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
    user-select: none;
}

.tree-host:hover {
    background: #e7f3ff;
}

.tree-host.active {
    background: #007bff;
    color: #fff;
    font-weight: 500;
}

.tree-host-icon {
    font-size: 11px;
    flex-shrink: 0;
}

.tree-host-toggle {
    font-size: 9px;
    transition: transform 0.2s;
    display: inline-block;
    flex-shrink: 0;
}

.tree-host-toggle.expanded {
    transform: rotate(90deg);
}

/* 第三级：分类（Application / Tag） */
.tree-categories {
    display: none;
    margin-left: 16px;
    padding-top: 2px;
}

.tree-categories.expanded {
    display: block;
}

.tree-category {
    padding: 4px 10px;
    margin: 1px 0;
    cursor: pointer;
    border-radius: 3px;
    font-size: 12px;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    user-select: none;
}

.tree-category:hover {
    background: #e7f3ff;
    color: #495057;
}

.tree-category.active {
    background: #007bff;
    color: #fff;
}

.tree-category-icon {
    font-size: 11px;
    flex-shrink: 0;
}

.tree-category-badge {
    font-size: 10px;
    background: #e9ecef;
    color: #6c757d;
    padding: 1px 6px;
    border-radius: 10px;
    flex-shrink: 0;
}

.tree-category.active .tree-category-badge {
    background: rgba(255,255,255,0.3);
    color: #fff;
}

.tree-category-loading {
    padding: 8px 10px;
    margin-left: 16px;
    font-size: 12px;
    color: #adb5bd;
}

/* ===== 右侧内容面板 ===== */
.content-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
}

.content-toolbar {
    padding: 10px 20px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.toolbar-label {
    font-size: 13px;
    font-weight: 600;
    color: #495057;
}

.toolbar-select {
    padding: 6px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
    height: 34px;
}

.toolbar-select:focus {
    border-color: #007bff;
    outline: none;
}

.toolbar-separator {
    width: 1px;
    height: 24px;
    background: #dee2e6;
}

.toolbar-btn {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    color: #495057;
    height: 34px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.toolbar-btn:hover {
    background: #e9ecef;
}

.toolbar-info {
    font-size: 12px;
    color: #6c757d;
    margin-left: auto;
}

.content-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

/* ===== 图表网格 ===== */
.graphs-grid {
    display: grid;
    grid-template-columns: repeat(var(--graph-cols, 2), 1fr);
    gap: 16px;
}

.graph-card {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    transition: box-shadow 0.2s;
}

.graph-card:hover {
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.graph-card-header {
    padding: 8px 12px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.graph-card-type {
    font-size: 10px;
    font-weight: 400;
    padding: 2px 6px;
    border-radius: 3px;
    flex-shrink: 0;
}

.graph-card-type.type-graph {
    background: #d4edda;
    color: #155724;
}

.graph-card-type.type-adhoc {
    background: #fff3cd;
    color: #856404;
}

.graph-card-body {
    cursor: pointer;
    position: relative;
    min-height: 100px;
    background: #fafafa;
}

.graph-card-body img {
    width: 100%;
    height: auto;
    display: block;
}

.graph-card-body .graph-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: #adb5bd;
    font-size: 13px;
}

/* ===== 空状态 ===== */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 15px;
}

/* ===== 加载状态 ===== */
.loading-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    animation: spin 1s linear infinite;
    margin: 0 auto 12px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ===== 图表放大弹窗 ===== */
.graph-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.graph-modal {
    background: #fff;
    border-radius: 8px;
    width: 92vw;
    max-width: 1800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.graph-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
    flex-shrink: 0;
}

.graph-modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.graph-modal-close {
    background: none;
    border: none;
    font-size: 24px;
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
    overflow: auto;
    padding: 16px;
    text-align: center;
}

.graph-modal-body img {
    max-width: 100%;
    height: auto;
}

/* ===== 响应式 ===== */
@media (max-width: 1200px) {
    .graphs-grid {
        --graph-cols: 1 !important;
    }
}

@media (max-width: 768px) {
    .graphtrees-container {
        flex-direction: column;
    }
    .tree-panel {
        width: 100%;
        min-width: unset;
        height: 300px;
        border-right: none;
        border-bottom: 1px solid #dee2e6;
    }
}
');

// ===== 构建树形面板 =====
$treePanel = (new CDiv())->addClass('tree-panel');

// 树头部
$treeHeader = (new CDiv())
    ->addClass('tree-header')
    ->addItem(new CTag('h3', true, LanguageManager::t('Resource Tree')))
    ->addItem(
        (new CDiv())
            ->addClass('tree-controls')
            ->addItem(
                (new CButton('expand-all', LanguageManager::t('Expand All')))
                    ->setAttribute('style', 'font-size:11px;padding:4px 8px;border:1px solid #dee2e6;border-radius:3px;background:#f8f9fa;cursor:pointer;color:#495057;')
                    ->setAttribute('onclick', 'expandAllGroups()')
            )
            ->addItem(
                (new CButton('collapse-all', LanguageManager::t('Collapse All')))
                    ->setAttribute('style', 'font-size:11px;padding:4px 8px;border:1px solid #dee2e6;border-radius:3px;background:#f8f9fa;cursor:pointer;color:#495057;')
                    ->setAttribute('onclick', 'collapseAllGroups()')
            )
    );
$treePanel->addItem($treeHeader);

// 搜索框
$treeSearch = (new CDiv())
    ->addClass('tree-search')
    ->addItem(
        (new CTextBox('tree_search', ''))
            ->setAttribute('placeholder', LanguageManager::t('Search...'))
            ->setAttribute('oninput', 'filterTree(this.value)')
    );
$treePanel->addItem($treeSearch);

// 树节点
$treeNodes = (new CDiv())
    ->addClass('tree-nodes')
    ->setAttribute('id', 'tree-nodes');

if (!empty($data['tree_data'])) {
    foreach ($data['tree_data'] as $group) {
        $groupDiv = (new CDiv())->addClass('tree-group');

        // 分组头
        $groupHeader = (new CDiv())
            ->addClass('tree-group-header')
            ->setAttribute('onclick', 'toggleGroup(this)');

        $icon = (new CSpan("\u{25BC}"))->addClass('tree-group-icon collapsed');
        $groupHeader->addItem($icon);
        $groupHeader->addItem(new CSpan($group['groupname']));
        $groupDiv->addItem($groupHeader);

        // 主机列表（默认折叠）
        $hostsDiv = (new CDiv())->addClass('tree-hosts');

        foreach ($group['hosts'] as $host) {
            $hostWrapper = new CDiv();

            $hostDiv = (new CDiv())
                ->addClass('tree-host')
                ->setAttribute('data-hostid', $host['hostid'])
                ->setAttribute('data-hostname', $host['name'])
                ->setAttribute('onclick', 'selectHost(this, ' . $host['hostid'] . ', ' . json_encode($host['name']) . ')');

            $hostToggle = (new CSpan("\u{25B6}"))->addClass('tree-host-toggle');
            $hostDiv->addItem($hostToggle);
            $hostDiv->addItem((new CSpan("\u{1F5A5}"))->addClass('tree-host-icon'));
            $hostDiv->addItem(new CSpan($host['name']));
            $hostWrapper->addItem($hostDiv);

            // 分类容器（将通过AJAX填充）
            $catDiv = (new CDiv())
                ->addClass('tree-categories')
                ->setAttribute('id', 'categories-' . $host['hostid']);
            $hostWrapper->addItem($catDiv);

            $hostsDiv->addItem($hostWrapper);
        }

        $groupDiv->addItem($hostsDiv);
        $treeNodes->addItem($groupDiv);
    }
}

$treePanel->addItem($treeNodes);

// ===== 构建内容面板 =====
$contentPanel = (new CDiv())->addClass('content-panel');

// 工具栏
$toolbar = (new CDiv())
    ->addClass('content-toolbar')
    ->setAttribute('id', 'content-toolbar');

// 时间范围选择
$toolbar->addItem((new CSpan(LanguageManager::t('Time Range')))->addClass('toolbar-label'));

$timeSelect = new CTag('select', true);
$timeSelect->setAttribute('id', 'time-range-select');
$timeSelect->setAttribute('class', 'toolbar-select');
$timeSelect->setAttribute('onchange', 'onTimeRangeChange(this.value)');

$timeOptions = [
    'now-10m' => LanguageManager::t('Last 10 Minutes'),
    'now-30m' => LanguageManager::t('Last 30 Minutes'),
    'now-1h' => LanguageManager::t('Last Hour'),
    'now-3h' => LanguageManager::t('Last 3 Hours'),
    'now-6h' => LanguageManager::t('Last 6 Hours'),
    'now-12h' => LanguageManager::t('Last 12 Hours'),
    'now-1d' => LanguageManager::t('Last 24 Hours'),
    'now-2d' => LanguageManager::t('Last 2 Days'),
    'now-7d' => LanguageManager::t('Last 7 Days'),
    'now-30d' => LanguageManager::t('Last 30 Days')
];

foreach ($timeOptions as $value => $label) {
    $opt = (new CTag('option', true, $label))->setAttribute('value', $value);
    if ($value === 'now-1h') {
        $opt->setAttribute('selected', 'selected');
    }
    $timeSelect->addItem($opt);
}
$toolbar->addItem($timeSelect);

$toolbar->addItem((new CDiv())->addClass('toolbar-separator'));

// 列数选择
$toolbar->addItem((new CSpan(LanguageManager::t('Columns')))->addClass('toolbar-label'));

$colSelect = new CTag('select', true);
$colSelect->setAttribute('id', 'columns-select');
$colSelect->setAttribute('class', 'toolbar-select');
$colSelect->setAttribute('onchange', 'onColumnsChange(this.value)');

for ($c = 1; $c <= 4; $c++) {
    $opt = (new CTag('option', true, $c))->setAttribute('value', $c);
    if ($c === 2) {
        $opt->setAttribute('selected', 'selected');
    }
    $colSelect->addItem($opt);
}
$toolbar->addItem($colSelect);

$toolbar->addItem((new CDiv())->addClass('toolbar-separator'));

// 自动刷新
$toolbar->addItem((new CSpan(LanguageManager::t('Auto Refresh')))->addClass('toolbar-label'));

$refreshSelect = new CTag('select', true);
$refreshSelect->setAttribute('id', 'auto-refresh-select');
$refreshSelect->setAttribute('class', 'toolbar-select');
$refreshSelect->setAttribute('onchange', 'onAutoRefreshChange(this.value)');

$refreshOptions = [
    '0' => LanguageManager::t('Off'),
    '30' => '30 ' . LanguageManager::t('seconds'),
    '60' => '60 ' . LanguageManager::t('seconds'),
    '120' => '120 ' . LanguageManager::t('seconds'),
    '300' => '300 ' . LanguageManager::t('seconds')
];

foreach ($refreshOptions as $value => $label) {
    $opt = (new CTag('option', true, $label))->setAttribute('value', $value);
    if ($value === '60') {
        $opt->setAttribute('selected', 'selected');
    }
    $refreshSelect->addItem($opt);
}
$toolbar->addItem($refreshSelect);

// 手动刷新按钮
$toolbar->addItem(
    (new CButton('refresh-btn', LanguageManager::t('Refresh')))
        ->addClass('toolbar-btn')
        ->setAttribute('onclick', 'refreshGraphs()')
);

// 信息栏
$toolbar->addItem(
    (new CSpan(''))->addClass('toolbar-info')->setAttribute('id', 'toolbar-info')
);

$contentPanel->addItem($toolbar);

// 内容主体
$contentBody = (new CDiv())
    ->addClass('content-body')
    ->setAttribute('id', 'content-body');

// 默认空状态
$emptyState = (new CDiv())
    ->addClass('empty-state')
    ->setAttribute('id', 'empty-state')
    ->addItem((new CDiv("\u{1F333}"))->addClass('empty-state-icon'))
    ->addItem((new CDiv(LanguageManager::t('Select a host to view graphs')))->addClass('empty-state-text'));

$contentBody->addItem($emptyState);

// 图表网格容器
$graphsGrid = (new CDiv())
    ->addClass('graphs-grid')
    ->setAttribute('id', 'graphs-grid')
    ->setAttribute('style', 'display:none;');

$contentBody->addItem($graphsGrid);

$contentPanel->addItem($contentBody);

// ===== 主容器 =====
$mainContainer = (new CDiv())
    ->addClass('graphtrees-container')
    ->addItem($treePanel)
    ->addItem($contentPanel);

// 渲染页面
ViewRenderer::render($pageTitle, $styleTag, $mainContainer);

// ===== JavaScript =====
$i18n = [
    'allGraphs' => LanguageManager::t('All Graphs'),
    'other' => LanguageManager::t('Other'),
    'loading' => LanguageManager::t('Loading...'),
    'noData' => LanguageManager::t('No graphs available'),
    'noCategories' => LanguageManager::t('No categories found'),
    'failedToLoad' => LanguageManager::t('Failed to load data'),
    'selectHost' => LanguageManager::t('Select a host to view graphs'),
    'close' => LanguageManager::t('Close'),
    'preConfigured' => LanguageManager::t('Pre-configured'),
    'autoGenerated' => LanguageManager::t('Auto-generated'),
    'graphsCount' => LanguageManager::t('graphs'),
    'refresh' => LanguageManager::t('Refresh')
];
?>
<script>
var i18n = <?php echo json_encode($i18n, JSON_UNESCAPED_UNICODE); ?>;

// ===== 状态管理 =====
var state = {
    selectedHostId: 0,
    selectedHostName: "",
    selectedCategory: "",
    timeFrom: "now-1h",
    timeTo: "now",
    columns: 2,
    autoRefreshTimer: null,
    autoRefreshSeconds: 60,
    loadedCategories: {} // hostid -> categories data cache
};

// ===== 树形面板交互 =====
function toggleGroup(el) {
    var icon = el.querySelector(".tree-group-icon");
    var hostsDiv = el.parentElement.querySelector(".tree-hosts");
    if (hostsDiv.classList.contains("expanded")) {
        hostsDiv.classList.remove("expanded");
        icon.classList.add("collapsed");
    } else {
        hostsDiv.classList.add("expanded");
        icon.classList.remove("collapsed");
    }
}

function expandAllGroups() {
    document.querySelectorAll(".tree-hosts").forEach(function(d) { d.classList.add("expanded"); });
    document.querySelectorAll(".tree-group-icon").forEach(function(i) { i.classList.remove("collapsed"); });
}

function collapseAllGroups() {
    document.querySelectorAll(".tree-hosts").forEach(function(d) { d.classList.remove("expanded"); });
    document.querySelectorAll(".tree-group-icon").forEach(function(i) { i.classList.add("collapsed"); });
    document.querySelectorAll(".tree-categories").forEach(function(d) { d.classList.remove("expanded"); });
    document.querySelectorAll(".tree-host-toggle").forEach(function(t) { t.classList.remove("expanded"); });
}

function filterTree(text) {
    var lower = text.toLowerCase();
    document.querySelectorAll(".tree-group").forEach(function(group) {
        var visible = false;
        group.querySelectorAll(".tree-host").forEach(function(host) {
            var name = (host.getAttribute("data-hostname") || "").toLowerCase();
            if (name.indexOf(lower) !== -1 || !text) {
                host.parentElement.style.display = "";
                visible = true;
            } else {
                host.parentElement.style.display = "none";
            }
        });
        group.style.display = (visible || !text) ? "" : "none";
    });
}

function selectHost(el, hostid, hostname) {
    // 更新状态
    state.selectedHostId = hostid;
    state.selectedHostName = hostname;
    state.selectedCategory = "__all__";

    // 高亮主机
    document.querySelectorAll(".tree-host").forEach(function(h) { h.classList.remove("active"); });
    el.classList.add("active");

    // 清除分类高亮
    document.querySelectorAll(".tree-category").forEach(function(c) { c.classList.remove("active"); });

    // 展开分类
    var toggle = el.querySelector(".tree-host-toggle");
    var catContainer = document.getElementById("categories-" + hostid);

    if (toggle) toggle.classList.add("expanded");
    if (catContainer) catContainer.classList.add("expanded");

    // 折叠其他主机的分类
    document.querySelectorAll(".tree-categories").forEach(function(c) {
        if (c.id !== "categories-" + hostid) {
            c.classList.remove("expanded");
        }
    });
    document.querySelectorAll(".tree-host:not(.active)").forEach(function(h) {
        var t = h.querySelector(".tree-host-toggle");
        if (t) t.classList.remove("expanded");
    });

    // 加载分类（如果尚未加载）
    if (!state.loadedCategories[hostid]) {
        loadCategories(hostid);
    } else {
        renderCategories(hostid, state.loadedCategories[hostid]);
    }

    // 加载所有图表
    loadGraphs(hostid, "__all__");
}

function selectCategory(el, hostid, category) {
    state.selectedCategory = category;

    // 高亮分类
    document.querySelectorAll(".tree-category").forEach(function(c) { c.classList.remove("active"); });
    el.classList.add("active");

    // 加载该分类的图表
    loadGraphs(hostid, category);
}

// ===== AJAX: 加载分类 =====
function loadCategories(hostid) {
    var catContainer = document.getElementById("categories-" + hostid);
    if (!catContainer) return;

    catContainer.innerHTML = '<div class="tree-category-loading">' + i18n.loading + '</div>';

    fetch("?action=graphtrees.data&type=categories&hostid=" + hostid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                state.loadedCategories[hostid] = data;
                renderCategories(hostid, data);
            } else {
                catContainer.innerHTML = '<div class="tree-category-loading">' + i18n.failedToLoad + '</div>';
            }
        })
        .catch(function(err) {
            console.error("loadCategories error:", err);
            catContainer.innerHTML = '<div class="tree-category-loading">' + i18n.failedToLoad + '</div>';
        });
}

function renderCategories(hostid, data) {
    var catContainer = document.getElementById("categories-" + hostid);
    if (!catContainer) return;

    catContainer.innerHTML = "";

    // "所有图表"选项
    var allDiv = document.createElement("div");
    allDiv.className = "tree-category" + (state.selectedCategory === "__all__" ? " active" : "");
    allDiv.onclick = function() { selectCategory(this, hostid, "__all__"); };
    allDiv.innerHTML = '<span class="tree-category-icon">\u{1F4CA}</span>' +
        '<span style="flex:1">' + i18n.allGraphs + '</span>' +
        '<span class="tree-category-badge">' + (data.totalGraphs || 0) + '</span>';
    catContainer.appendChild(allDiv);

    // 各分类
    if (data.categories && data.categories.length > 0) {
        data.categories.forEach(function(cat) {
            var catDiv = document.createElement("div");
            var catKey = cat.key || cat.name;
            catDiv.className = "tree-category" + (state.selectedCategory === catKey ? " active" : "");
            catDiv.onclick = function() { selectCategory(this, hostid, catKey); };
            catDiv.innerHTML = '<span class="tree-category-icon">\u{1F4C1}</span>' +
                '<span style="flex:1">' + escapeHtml(cat.name) + '</span>' +
                '<span class="tree-category-badge">' + (cat.graphCount || 0) + '</span>';
            catContainer.appendChild(catDiv);
        });
    }
}

// ===== AJAX: 加载图表 =====
function loadGraphs(hostid, category) {
    var grid = document.getElementById("graphs-grid");
    var empty = document.getElementById("empty-state");

    empty.style.display = "none";
    grid.style.display = "grid";
    grid.innerHTML = '<div class="loading-state" style="grid-column:1/-1"><div class="spinner"></div><div>' + i18n.loading + '</div></div>';

    fetch("?action=graphtrees.data&type=graphs&hostid=" + hostid + "&category=" + encodeURIComponent(category))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.graphs && data.graphs.length > 0) {
                renderGraphs(data.graphs);
                updateToolbarInfo(data.graphs.length);
            } else {
                grid.innerHTML = "";
                grid.style.display = "none";
                empty.style.display = "";
                empty.querySelector(".empty-state-text").textContent = i18n.noData;
                empty.querySelector(".empty-state-icon").textContent = "\u{1F4CA}";
                updateToolbarInfo(0);
            }
        })
        .catch(function(err) {
            console.error("loadGraphs error:", err);
            grid.innerHTML = "";
            grid.style.display = "none";
            empty.style.display = "";
            empty.querySelector(".empty-state-text").textContent = i18n.failedToLoad;
            updateToolbarInfo(0);
        });
}

// ===== 渲染图表 =====
function renderGraphs(graphs) {
    var grid = document.getElementById("graphs-grid");
    grid.innerHTML = "";

    graphs.forEach(function(g) {
        var card = document.createElement("div");
        card.className = "graph-card";

        // 头部
        var header = document.createElement("div");
        header.className = "graph-card-header";

        var title = document.createElement("span");
        title.textContent = g.name;
        title.style.flex = "1";
        title.style.overflow = "hidden";
        title.style.textOverflow = "ellipsis";
        title.style.whiteSpace = "nowrap";
        title.title = g.name;
        header.appendChild(title);

        var typeBadge = document.createElement("span");
        typeBadge.className = "graph-card-type " + (g.type === "graph" ? "type-graph" : "type-adhoc");
        typeBadge.textContent = g.type === "graph" ? i18n.preConfigured : i18n.autoGenerated;
        header.appendChild(typeBadge);

        card.appendChild(header);

        // 图表图片
        var body = document.createElement("div");
        body.className = "graph-card-body";

        var imgSrc = buildGraphUrl(g, 1200, 300);
        var img = document.createElement("img");
        img.src = imgSrc;
        img.loading = "lazy";
        img.alt = g.name;
        img.onerror = function() {
            this.style.display = "none";
            var errDiv = document.createElement("div");
            errDiv.className = "graph-loading";
            errDiv.textContent = i18n.failedToLoad;
            this.parentElement.appendChild(errDiv);
        };

        body.appendChild(img);
        body.onclick = function() { openZoomModal(g); };
        card.appendChild(body);

        grid.appendChild(card);
    });
}

function buildGraphUrl(g, width, height) {
    var ts = Date.now();
    if (g.type === "graph") {
        return "chart2.php?graphid=" + g.graphid +
            "&from=" + encodeURIComponent(state.timeFrom) +
            "&to=" + encodeURIComponent(state.timeTo) +
            "&width=" + width + "&height=" + height +
            "&_=" + ts;
    } else {
        var url = "chart.php?from=" + encodeURIComponent(state.timeFrom) +
            "&to=" + encodeURIComponent(state.timeTo) +
            "&type=0&width=" + width + "&height=" + height;
        g.itemids.forEach(function(id, i) {
            url += "&itemids%5B" + i + "%5D=" + id;
        });
        url += "&_=" + ts;
        return url;
    }
}

// ===== 放大弹窗 =====
function openZoomModal(g) {
    var overlay = document.createElement("div");
    overlay.className = "graph-modal-overlay";
    overlay.id = "graph-modal-overlay";

    var modal = document.createElement("div");
    modal.className = "graph-modal";

    var header = document.createElement("div");
    header.className = "graph-modal-header";

    var title = document.createElement("h3");
    title.className = "graph-modal-title";
    title.textContent = g.name;
    header.appendChild(title);

    var closeBtn = document.createElement("button");
    closeBtn.className = "graph-modal-close";
    closeBtn.innerHTML = "\u00D7";
    closeBtn.title = i18n.close;
    closeBtn.onclick = closeZoomModal;
    header.appendChild(closeBtn);
    modal.appendChild(header);

    var body = document.createElement("div");
    body.className = "graph-modal-body";

    var img = document.createElement("img");
    img.src = buildGraphUrl(g, 1800, 500);
    img.alt = g.name;
    body.appendChild(img);
    modal.appendChild(body);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = "hidden";

    overlay.onclick = function(e) {
        if (e.target === overlay) closeZoomModal();
    };
    document.addEventListener("keydown", handleModalEsc);
}

function handleModalEsc(e) {
    if (e.key === "Escape") closeZoomModal();
}

function closeZoomModal() {
    var overlay = document.getElementById("graph-modal-overlay");
    if (overlay) {
        overlay.remove();
        document.body.style.overflow = "";
        document.removeEventListener("keydown", handleModalEsc);
    }
}

// ===== 工具栏功能 =====
function onTimeRangeChange(value) {
    state.timeFrom = value;
    state.timeTo = "now";
    refreshGraphImages();
}

function onColumnsChange(value) {
    state.columns = parseInt(value);
    var grid = document.getElementById("graphs-grid");
    if (grid) {
        grid.style.setProperty("--graph-cols", state.columns);
    }
}

function onAutoRefreshChange(value) {
    var seconds = parseInt(value);
    state.autoRefreshSeconds = seconds;
    stopAutoRefresh();
    if (seconds > 0) {
        startAutoRefresh(seconds);
    }
}

function startAutoRefresh(seconds) {
    stopAutoRefresh();
    state.autoRefreshTimer = setInterval(function() {
        if (state.selectedHostId > 0) {
            refreshGraphImages();
        }
    }, seconds * 1000);
}

function stopAutoRefresh() {
    if (state.autoRefreshTimer) {
        clearInterval(state.autoRefreshTimer);
        state.autoRefreshTimer = null;
    }
}

function refreshGraphs() {
    if (state.selectedHostId > 0) {
        // 清除分类缓存以强制刷新
        delete state.loadedCategories[state.selectedHostId];
        loadCategories(state.selectedHostId);
        loadGraphs(state.selectedHostId, state.selectedCategory);
    }
}

function refreshGraphImages() {
    // 只更新图片URL（改变时间戳参数强制刷新）
    var imgs = document.querySelectorAll("#graphs-grid .graph-card-body img");
    var ts = Date.now();
    imgs.forEach(function(img) {
        var src = img.src;
        // 替换 _= 参数
        src = src.replace(/[&?]_=\d+/, "");
        src += (src.indexOf("?") !== -1 ? "&" : "?") + "_=" + ts;
        // 更新时间范围
        src = src.replace(/from=[^&]+/, "from=" + encodeURIComponent(state.timeFrom));
        src = src.replace(/to=[^&]+/, "to=" + encodeURIComponent(state.timeTo));
        img.src = src;
    });
}

function updateToolbarInfo(count) {
    var info = document.getElementById("toolbar-info");
    if (info) {
        info.textContent = state.selectedHostName + " - " + count + " " + i18n.graphsCount;
    }
}

// ===== 工具函数 =====
function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
}

// ===== 初始化 =====
document.addEventListener("DOMContentLoaded", function() {
    // 设置列数CSS变量
    var grid = document.getElementById("graphs-grid");
    if (grid) {
        grid.style.setProperty("--graph-cols", state.columns);
    }

    // 启动默认自动刷新
    if (state.autoRefreshSeconds > 0) {
        startAutoRefresh(state.autoRefreshSeconds);
    }
});
</script>
