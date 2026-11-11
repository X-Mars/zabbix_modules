<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixLldp\Lib\LanguageManager;
use Modules\ZabbixLldp\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('LLDP Topology');
$hostGroups = $data['host_groups'] ?? [];
$hosts = $data['hosts'] ?? [];
$selectedGroupid = (string) ($data['selected_groupid'] ?? '');
$selectedHostid = (string) ($data['selected_hostid'] ?? '');
$templateFound = !empty($data['template_found']);

$styleTag = new CTag('style', true, '
.lldp-page {
    padding: 20px;
    width: 100%;
    min-height: calc(100vh - 64px);
    box-sizing: border-box;
}
.lldp-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
    padding: 16px 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 16px;
}
.lldp-field {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}
.lldp-field label {
    font-weight: 600;
    font-size: 14px;
    color: #495057;
    margin-bottom: 5px;
}
.lldp-field select {
    height: 38px;
    padding: 0 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 15px;
    background: #fff;
}
.lldp-field input {
    height: 38px;
    padding: 0 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 15px;
    background: #fff;
}
.lldp-btn {
    height: 38px;
    padding: 0 20px;
    border: none;
    border-radius: 4px;
    background: #1b6ec2;
    color: #fff;
    font-size: 15px;
    cursor: pointer;
}
.lldp-btn.lldp-btn-light {
    background: #f0f4f8;
    color: #334e68;
    border: 1px solid #ced4da;
}
.lldp-btn.lldp-btn-light:hover:not(:disabled) {
    background: #e4ebf3;
    border-color: #9fb3c8;
}
.lldp-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.lldp-stats {
    margin-left: auto;
    font-size: 14px;
    color: #486581;
    align-self: center;
}
.lldp-alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
    font-size: 14px;
    border: 1px solid #ffeeba;
    background: #fff3cd;
    color: #856404;
}
.lldp-canvas-wrap {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    height: calc(100vh - 220px);
    min-height: 420px;
    overflow: auto;
    position: relative;
}
.lldp-detail-muted {
    color: #829ab1;
}
.lldp-detail-list {
    margin: 8px 0 0;
    padding-left: 16px;
}
.lldp-detail-list li {
    margin-bottom: 4px;
}
.lldp-canvas-wrap.lldp-pinchable {
    touch-action: none;
    cursor: grab;
}
.lldp-canvas-wrap.lldp-dragging {
    cursor: grabbing;
}
.lldp-canvas-wrap.lldp-dragging .lldp-node circle,
.lldp-canvas-wrap.lldp-dragging .lldp-edge {
    cursor: grabbing;
}
.lldp-zoom-controls {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lldp-zoom-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background: #fff;
    color: #334e68;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}
.lldp-zoom-btn:hover:not(:disabled) {
    background: #f0f4f8;
    border-color: #1b6ec2;
    color: #1b6ec2;
}
.lldp-zoom-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.lldp-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 420px;
    color: #829ab1;
    font-size: 16px;
}
.lldp-svg {
    display: block;
    min-width: 100%;
    min-height: 100%;
}
#lldp-zoom-root {
    display: block;
    min-width: 100%;
    min-height: 100%;
}
.lldp-node {
    cursor: grab;
}
.lldp-node circle {
    stroke: #334e68;
    stroke-width: 2;
    cursor: grab;
}
.lldp-node.lldp-node-dragging {
    cursor: grabbing;
}
.lldp-node.lldp-node-dragging circle {
    cursor: grabbing;
    stroke: #1b6ec2;
    stroke-width: 3;
}
.lldp-node.local circle {
    fill: #1b6ec2;
}
.lldp-node.remote circle {
    fill: #627d98;
}
.lldp-node.severity-none circle {
    fill: #2f855a;
}
.lldp-node.severity-0 circle {
    fill: #97aab3;
}
.lldp-node.severity-1 circle {
    fill: #7499ff;
}
.lldp-node.severity-2 circle {
    fill: #ffc859;
}
.lldp-node.severity-3 circle {
    fill: #ffa059;
}
.lldp-node.severity-4 circle {
    fill: #e97659;
}
.lldp-node.severity-5 circle {
    fill: #e45959;
}
.lldp-node text {
    font-size: 13px;
    fill: #243b53;
    text-anchor: middle;
    dominant-baseline: middle;
    paint-order: stroke;
    stroke: #ffffff;
    stroke-width: 3px;
    stroke-linejoin: round;
    pointer-events: none;
}

.lldp-region {
    fill: rgba(99, 133, 171, .07);
    stroke: rgba(99, 133, 171, .40);
    pointer-events: none;
}
.lldp-region-label { fill: currentColor; font-size: 12px; pointer-events: none; }
.lldp-edge.lldp-redundant { stroke-dasharray: 4 5; }
.lldp-toggle { cursor: pointer; }
.lldp-toggle rect { fill: #e8f1ff; stroke: #608dc6; }
.lldp-toggle.lldp-hidden-alert rect { fill: #fff0d6; stroke: #ad620e; }
.lldp-toggle text { fill: #203b60; stroke: none; font-size: 12px; pointer-events: none; }
.lldp-toggle:focus rect { stroke-width: 3px; }

.lldp-edge {
    stroke: #bcccdc;
    stroke-width: 1.5;
    fill: none;
    cursor: pointer;
}
.lldp-edge-hit {
    stroke: transparent;
    stroke-width: 12;
    fill: none;
    cursor: pointer;
}
.lldp-edge:hover,
.lldp-edge.lldp-active {
    stroke: #1b6ec2;
    stroke-width: 2;
}
.lldp-edge.lldp-muted {
    opacity: 0.18;
}
.lldp-edge-hit.lldp-muted {
    pointer-events: none;
}
.lldp-node.lldp-muted {
    opacity: 0.32;
}
.lldp-tooltip {
    position: fixed;
    display: none;
    z-index: 3000;
    max-width: 320px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.12);
    font-size: 13px;
    color: #243b53;
    line-height: 1.5;
    pointer-events: none;
}
.lldp-tooltip strong {
    color: #334e68;
}
');

$content = (new CDiv())->addClass('lldp-page');

if (!$templateFound) {
    $content->addItem(
        (new CDiv('⚠️ ' . LanguageManager::t('Template not found')))
            ->addClass('lldp-alert')
    );
}

$form = (new CForm('get'))
    ->setAction('zabbix.php?action=lldp')
    ->addItem((new CInput('hidden', 'action', 'lldp')));

$toolbar = (new CDiv())->addClass('lldp-toolbar');

$groupField = (new CDiv())->addClass('lldp-field');
$groupField->addItem(new CLabel('📂 ' . LanguageManager::t('Select host group')));
$groupSelect = (new CTag('select', true))
    ->setAttribute('name', 'groupid')
    ->setAttribute('id', 'lldp-group-select')
    ->setAttribute('onchange', 'lldpOnGroupChange(this)');
$groupSelect->addItem(
    (new CTag('option', true, LanguageManager::t('All Groups')))->setAttribute('value', '0')
);
foreach ($hostGroups as $group) {
    $opt = (new CTag('option', true, $group['name']))
        ->setAttribute('value', (string) $group['groupid']);
    if ($selectedGroupid !== '' && $selectedGroupid === (string) $group['groupid']) {
        $opt->setAttribute('selected', 'selected');
    }
    $groupSelect->addItem($opt);
}
$groupField->addItem($groupSelect);
$toolbar->addItem($groupField);

$hostField = (new CDiv())->addClass('lldp-field');
$hostField->addItem(new CLabel('🖥️ ' . LanguageManager::t('Select host')));
$hostSelect = (new CTag('select', true))
    ->setAttribute('name', 'hostid')
    ->setAttribute('id', 'lldp-host-select');
$hostSelect->addItem(
    (new CTag('option', true, LanguageManager::t('All Hosts')))->setAttribute('value', '0')
);
foreach ($hosts as $host) {
    $label = $host['name'] !== '' ? $host['name'] : $host['host'];
    $opt = (new CTag('option', true, $label))->setAttribute('value', (string) $host['hostid']);
    if ($selectedHostid !== '' && $selectedHostid === (string) $host['hostid']) {
        $opt->setAttribute('selected', 'selected');
    }
    $hostSelect->addItem($opt);
}
$hostField->addItem($hostSelect);
$toolbar->addItem($hostField);

$queryBtn = (new CTag('button', true, '🔍 ' . LanguageManager::t('Query')))
    ->addClass('lldp-btn')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'lldp-query-btn');
if (!$templateFound) {
    $queryBtn->setAttribute('disabled', 'disabled');
}
$toolbar->addItem($queryBtn);

$filterField = (new CDiv())->addClass('lldp-field');
$filterField->addItem(new CLabel('🧭 ' . LanguageManager::t('Link filter')));
$filterInput = (new CInput('text', 'link_filter', ''))
    ->setAttribute('id', 'lldp-link-filter')
    ->setAttribute('placeholder', LanguageManager::t('Filter links by device or port'));
$filterField->addItem($filterInput);
$toolbar->addItem($filterField);

$filterClearBtn = (new CTag('button', true, LanguageManager::t('Clear filter')))
    ->addClass('lldp-btn lldp-btn-light')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'lldp-filter-clear');
$toolbar->addItem($filterClearBtn);

$toolbar->addItem(
    (new CDiv(''))->addClass('lldp-stats')->setAttribute('id', 'lldp-stats')
);

$form->addItem($toolbar);
$content->addItem($form);

$canvasWrap = (new CDiv())->addClass('lldp-canvas-wrap')->setAttribute('id', 'lldp-canvas-wrap');

$zoomControls = (new CDiv())->addClass('lldp-zoom-controls');
$zoomInBtn = (new CTag('button', true, '+'))
    ->addClass('lldp-zoom-btn')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'lldp-zoom-in')
    ->setAttribute('title', LanguageManager::t('Zoom in'))
    ->setAttribute('disabled', 'disabled');
$zoomOutBtn = (new CTag('button', true, '−'))
    ->addClass('lldp-zoom-btn')
    ->setAttribute('type', 'button')
    ->setAttribute('id', 'lldp-zoom-out')
    ->setAttribute('title', LanguageManager::t('Zoom out'))
    ->setAttribute('disabled', 'disabled');
$zoomControls->addItem($zoomInBtn);
$zoomControls->addItem($zoomOutBtn);
$canvasWrap->addItem($zoomControls);

$canvasWrap->addItem(
    (new CDiv(LanguageManager::t('Click query to load topology')))
        ->addClass('lldp-placeholder')
        ->setAttribute('id', 'lldp-placeholder')
);

$content->addItem($canvasWrap);

$content->addItem(
    (new CDiv())->addClass('lldp-tooltip')->setAttribute('id', 'lldp-tooltip')
);

$jsLabels = json_encode([
    'expandBranch' => LanguageManager::t('Show %d more devices'),
    'collapseBranch' => LanguageManager::t('Collapse branch'),
    'hiddenAlert' => LanguageManager::t('Hidden device alert'),
    'visibleDevices' => LanguageManager::t('Visible devices: %d'),
    'querying'    => LanguageManager::t('Querying...'),
    'query'       => LanguageManager::t('Query'),
    'queryFailed' => LanguageManager::t('Query failed'),
    'statsFormat' => LanguageManager::t('Stats format'),
    'placeholder' => LanguageManager::t('Click query to load topology'),
    'noData'      => LanguageManager::t('No LLDP data found'),
    'localDevice' => LanguageManager::t('Local device'),
    'remoteDevice'=> LanguageManager::t('Remote device'),
    'localPort'   => LanguageManager::t('Local port'),
    'remotePort'  => LanguageManager::t('Remote port'),
    'lastUpdate'  => LanguageManager::t('Last update'),
    'local'       => LanguageManager::t('Local'),
    'remote'      => LanguageManager::t('Remote'),
    'skipped'     => LanguageManager::t('Skipped items'),
    'zoomIn'      => LanguageManager::t('Zoom in'),
    'zoomOut'     => LanguageManager::t('Zoom out'),
    'matchedLinks'=> LanguageManager::t('Matched links'),
    'hostName'    => LanguageManager::t('Host name'),
    'problemCount'=> LanguageManager::t('Problem count'),
    'noActiveProblem' => LanguageManager::t('No active problem'),
    'problemDetails' => LanguageManager::t('Problem details'),
    'severity0' => LanguageManager::t('Severity: Not classified'),
    'severity1' => LanguageManager::t('Severity: Information'),
    'severity2' => LanguageManager::t('Severity: Warning'),
    'severity3' => LanguageManager::t('Severity: Average'),
    'severity4' => LanguageManager::t('Severity: High'),
    'severity5' => LanguageManager::t('Severity: Disaster'),
], JSON_UNESCAPED_UNICODE);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var labels = ' . $jsLabels . ';
    var NODE_R = 10;
    var LABEL_LINE_HEIGHT = 13;
    var LABEL_MAX_CHARS = 12;
    var ZOOM_MIN = 0.1;
    var ZOOM_MAX = 2.5;
    var ZOOM_STEP = 1.1;
    var AUTO_REFRESH_MS = 5000;
    var WHEEL_ZOOM_SENSITIVITY = 0.00065;
    var PINCH_ZOOM_SENSITIVITY = 0.55;

    var queryBtn = document.getElementById("lldp-query-btn");
    var statsEl = document.getElementById("lldp-stats");
    var filterInput = document.getElementById("lldp-link-filter");
    var filterClearBtn = document.getElementById("lldp-filter-clear");
    var canvasWrap = document.getElementById("lldp-canvas-wrap");
    var placeholder = document.getElementById("lldp-placeholder");
    var tooltip = document.getElementById("lldp-tooltip");
    var zoomInBtn = document.getElementById("lldp-zoom-in");
    var zoomOutBtn = document.getElementById("lldp-zoom-out");

    var currentZoom = 1;
    var panX = 0;
    var panY = 0;
    var zoomRoot = null;
    var zoomLayout = null;
    var zoomSvg = null;
    var pinchActive = false;
    var pinchStartDistance = 0;
    var pinchStartZoom = 1;
    var pinchStartPanX = 0;
    var pinchStartPanY = 0;
    var dragActive = false;
    var dragStartX = 0;
    var dragStartY = 0;
    var dragStartPanX = 0;
    var dragStartPanY = 0;
    var touchPanActive = false;
    var touchPanLastX = 0;
    var touchPanLastY = 0;
    var nodeDragActive = false;
    var nodeDragId = null;
    var nodeDragStartX = 0;
    var nodeDragStartY = 0;
    var nodeDragOrigX = 0;
    var nodeDragOrigY = 0;
    var topologyRuntime = null;
    var topologyData = null;
    var topologyGraph = null;
    var expandedNodes = new Set();
    var queryScope = null;
    var baseStatsText = "";
    var isQuerying = false;
    var autoRefreshTimer = null;
    var hasUserAdjustedView = false;

    window.lldpOnGroupChange = function(select) {
        var form = select.closest("form");
        var hostSelect = document.getElementById("lldp-host-select");
        if (hostSelect) { hostSelect.value = "0"; }
        if (form) { form.submit(); }
    };

    function formatStats(stats) {
        if (!stats) { return ""; }
        return labels.statsFormat
            .replace("%d", String(stats.hosts || 0))
            .replace("%d", String(stats.links || 0))
            .replace("%d", String(stats.devices || 0));
    }

    function formatTime(ts) {
        if (!ts) { return "-"; }
        var d = new Date(ts * 1000);
        if (isNaN(d.getTime())) { return "-"; }
        return d.toLocaleString();
    }

    function hideTooltip() {
        if (tooltip) { tooltip.style.display = "none"; }
    }

    function showTooltip(html, evt) {
        if (!tooltip) { return; }
        tooltip.innerHTML = html;
        tooltip.style.display = "block";
        var x = evt.clientX + 12;
        var y = evt.clientY + 12;
        if (x + 280 > window.innerWidth) { x = evt.clientX - 280; }
        if (y + 120 > window.innerHeight) { y = evt.clientY - 120; }
        tooltip.style.left = x + "px";
        tooltip.style.top = y + "px";
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/\'/g, "&#39;");
    }

    function getSeverityClass(node) {
        if (!node || !node.hostid) {
            return "";
        }
        var sev = Number(node.severity);
        if (isNaN(sev) || sev < 0) {
            return "severity-none";
        }
        if (sev > 5) {
            sev = 5;
        }
        return "severity-" + sev;
    }

    function getSeverityLabel(sev) {
        switch (Number(sev)) {
            case 0: return labels.severity0;
            case 1: return labels.severity1;
            case 2: return labels.severity2;
            case 3: return labels.severity3;
            case 4: return labels.severity4;
            case 5: return labels.severity5;
            default: return labels.noActiveProblem;
        }
    }

    function buildNodeTooltip(node, kindLabel) {
        var html = "<strong>" + escapeHtml(kindLabel) + "</strong><br>"
            + "<strong>" + escapeHtml(labels.hostName) + "</strong> " + escapeHtml(node.host || node.label || "-") + "<br>"
            + "<strong>" + escapeHtml(labels.problemCount) + "</strong> " + String(node.problemCount || 0) + "<br>"
            + "<strong>" + escapeHtml(getSeverityLabel(node.severity)) + "</strong>";

        if (Array.isArray(node.problems) && node.problems.length > 0) {
            html += "<br><strong>" + escapeHtml(labels.problemDetails) + "</strong>";
            node.problems.slice(0, 3).forEach(function(p) {
                html += "<br>- [" + escapeHtml(getSeverityLabel(p.severity)) + "] "
                    + escapeHtml(p.name || "")
                    + " (" + escapeHtml(formatTime(p.clock)) + ")";
            });
        }

        return html;
    }

    function edgeMatchesFilter(edge, q) {
        if (!q) { return true; }
        var fields = [
            edge.localDevice || "",
            edge.remoteDevice || "",
            edge.localPort || "",
            edge.remotePort || ""
        ];

        function fuzzyContains(haystack, needle) {
            var h = String(haystack || "").toLowerCase();
            var n = String(needle || "").toLowerCase().trim();
            if (!n) { return true; }

            if (h.indexOf(n) !== -1) {
                return true;
            }

            var tokens = n.split(/\s+/).filter(function(t) { return t !== ""; });
            if (tokens.length > 1) {
                var allTokensMatched = tokens.every(function(t) {
                    return h.indexOf(t) !== -1;
                });
                if (allTokensMatched) {
                    return true;
                }
            }

            var hc = h.replace(/\s+/g, "");
            var nc = n.replace(/\s+/g, "");
            if (!nc) { return true; }

            var i = 0;
            for (var j = 0; j < hc.length && i < nc.length; j++) {
                if (hc.charAt(j) === nc.charAt(i)) {
                    i++;
                }
            }

            return i === nc.length;
        }

        var hay = fields.join(" ");
        if (fuzzyContains(hay, q)) {
            return true;
        }

        return fields.some(function(field) {
            return fuzzyContains(field, q);
        });
    }

    function applyEdgeFilter() {
        if (!topologyRuntime) { return; }
        var query = (filterInput ? filterInput.value : "");
        query = String(query || "").trim().toLowerCase();

        var activeNodeIds = {};
        var matched = 0;

        topologyRuntime.edgeRegistry.forEach(function(rec) {
            var isMatch = edgeMatchesFilter(rec.edge, query);
            rec.line.classList.toggle("lldp-muted", !isMatch);
            rec.hit.classList.toggle("lldp-muted", !isMatch);
            if (isMatch) {
                matched++;
                activeNodeIds[rec.edge.source] = true;
                activeNodeIds[rec.edge.target] = true;
            }
        });

        Object.keys(topologyRuntime.nodeRegistry || {}).forEach(function(nodeId) {
            var nodeEntry = topologyRuntime.nodeRegistry[nodeId];
            var dimmed = query !== "" && !activeNodeIds[nodeId];
            nodeEntry.group.classList.toggle("lldp-muted", dimmed);
        });

        if (statsEl) {
            var summary = baseStatsText + " / " + labels.visibleDevices.replace("%d",
                String(Object.keys(topologyRuntime.nodeRegistry).length));
            if (query !== "") {
                statsEl.textContent = summary + " / " + labels.matchedLinks.replace("%d", String(matched));
            } else {
                statsEl.textContent = summary;
            }
        }
    }

    function setZoomControlsEnabled(enabled) {
        if (!zoomInBtn || !zoomOutBtn) { return; }
        if (!enabled) {
            zoomInBtn.disabled = true;
            zoomOutBtn.disabled = true;
            return;
        }
        updateZoomButtons();
    }

    function updateZoomButtons() {
        if (!zoomInBtn || !zoomOutBtn) { return; }
        zoomInBtn.disabled = !zoomRoot || currentZoom >= ZOOM_MAX - 0.001;
        zoomOutBtn.disabled = !zoomRoot || currentZoom <= ZOOM_MIN + 0.001;
    }

    function renderTransform() {
        if (!zoomRoot || !zoomLayout || !zoomSvg) { return; }
        var cx = zoomLayout.cx;
        var cy = zoomLayout.cy;
        zoomRoot.setAttribute(
            "transform",
            "translate(" + panX + "," + panY + ") translate(" + cx + "," + cy + ") scale(" + currentZoom + ") translate(" + (-cx) + "," + (-cy) + ")"
        );
        var targetWidth = zoomLayout.width * currentZoom;
        var targetHeight = zoomLayout.height * currentZoom;
        if (canvasWrap) {
            targetWidth = Math.max(targetWidth, canvasWrap.clientWidth || 0);
            targetHeight = Math.max(targetHeight, canvasWrap.clientHeight || 0);
        }
        zoomSvg.setAttribute("width", targetWidth);
        zoomSvg.setAttribute("height", targetHeight);
        zoomRoot.setAttribute("width", targetWidth);
        zoomRoot.setAttribute("height", targetHeight);
        zoomRoot.setAttribute("viewBox", "0 0 " + zoomLayout.width + " " + zoomLayout.height);
        updateZoomButtons();
    }

    function getFitZoom(layout) {
        if (!canvasWrap || !layout) { return 1; }
        var vw = canvasWrap.clientWidth || layout.width || 1;
        var vh = canvasWrap.clientHeight || layout.height || 1;
        var lw = layout.width || 1;
        var lh = layout.height || 1;
        var fit = Math.min(vw / lw, vh / lh);
        fit = Math.min(1, fit);
        if (!isFinite(fit) || fit <= 0) {
            return 1;
        }
        return Math.max(ZOOM_MIN, fit);
    }

    function captureViewState() {
        if (!topologyRuntime || !topologyRuntime.layout) {
            return null;
        }

        var positions = {};
        Object.keys(topologyRuntime.layout.positions || {}).forEach(function(nodeId) {
            var p = topologyRuntime.layout.positions[nodeId];
            if (!p) { return; }
            positions[nodeId] = { x: p.x, y: p.y };
        });

        return {
            zoom: currentZoom,
            panX: panX,
            panY: panY,
            positions: positions,
            signature: topologyRuntime.layout.signature
        };
    }

    function applySavedNodePositions(layout, nodes, savedState) {
        if (!layout || !savedState || !savedState.positions) { return; }
        nodes.forEach(function(node) {
            var sp = savedState.positions[node.id];
            if (!sp) { return; }
            layout.positions[node.id] = { x: sp.x, y: sp.y };
        });
    }

    function clientToViewBox(clientX, clientY) {
        if (!canvasWrap) { return { x: 0, y: 0 }; }
        var rect = canvasWrap.getBoundingClientRect();
        return {
            x: (clientX - rect.left + canvasWrap.scrollLeft) / currentZoom,
            y: (clientY - rect.top + canvasWrap.scrollTop) / currentZoom
        };
    }

    function applyZoom(scale, originClient) {
        if (!zoomRoot || !zoomLayout || !zoomSvg) { return; }
        var newZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, scale));
        var cx = zoomLayout.cx;
        var cy = zoomLayout.cy;

        if (originClient) {
            var pt = clientToViewBox(originClient.clientX, originClient.clientY);
            panX += (currentZoom - newZoom) * (pt.x - cx);
            panY += (currentZoom - newZoom) * (pt.y - cy);
        }

        currentZoom = newZoom;
        renderTransform();
    }

    function setPinchable(enabled) {
        if (!canvasWrap) { return; }
        if (enabled) {
            canvasWrap.classList.add("lldp-pinchable");
        } else {
            canvasWrap.classList.remove("lldp-pinchable");
        }
    }

    function resetZoomState() {
        currentZoom = 1;
        panX = 0;
        panY = 0;
        pinchActive = false;
        pinchStartDistance = 0;
        pinchStartZoom = 1;
        pinchStartPanX = 0;
        pinchStartPanY = 0;
        dragActive = false;
        touchPanActive = false;
        nodeDragActive = false;
        nodeDragId = null;
        topologyRuntime = null;
        baseStatsText = "";
        if (canvasWrap) {
            canvasWrap.classList.remove("lldp-dragging");
        }
        zoomRoot = null;
        zoomLayout = null;
        zoomSvg = null;
        setPinchable(false);
        setZoomControlsEnabled(false);
    }

    function getTouchDistance(touches) {
        var dx = touches[0].clientX - touches[1].clientX;
        var dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function getTouchCenter(touches) {
        return {
            clientX: (touches[0].clientX + touches[1].clientX) / 2,
            clientY: (touches[0].clientY + touches[1].clientY) / 2
        };
    }

    function isZoomControlTarget(el) {
        return !!(el && el.closest && el.closest(".lldp-zoom-controls"));
    }

    function isNodeTarget(el) {
        return !!(el && el.closest && el.closest(".lldp-node"));
    }

    function computeLabelPosition(x, y, nodeR) {
        return { x: x, y: y + nodeR + 22, anchor: "middle" };
    }

    function splitLabelLines(text, maxChars) {
        var src = String(text || "").replace(/\s+/g, " ").trim();
        if (!src) { return [""]; }
        if (src.length <= maxChars) { return [src]; }

        var lines = [];
        var words = src.split(" ");

        if (words.length > 1) {
            var line = "";
            words.forEach(function(word) {
                var next = line ? (line + " " + word) : word;
                if (next.length <= maxChars) {
                    line = next;
                    return;
                }
                if (line) {
                    lines.push(line);
                    line = "";
                }
                while (word.length > maxChars) {
                    lines.push(word.slice(0, maxChars));
                    word = word.slice(maxChars);
                }
                line = word;
            });
            if (line) {
                lines.push(line);
            }
        } else {
            for (var i = 0; i < src.length; i += maxChars) {
                lines.push(src.slice(i, i + maxChars));
            }
        }

        return lines;
    }

    function setMultilineText(textEl, label, x, y, maxChars, lineHeight, anchor) {
        if (!textEl) { return; }
        while (textEl.firstChild) {
            textEl.removeChild(textEl.firstChild);
        }

        var lines = splitLabelLines(label, maxChars);
        if (lines.length > 3) {
            lines = lines.slice(0, 3);
            lines[2] = lines[2].slice(0, maxChars - 1) + "…";
        }
        var total = lines.length;
        var startDy = -((total - 1) * lineHeight) / 2;

        textEl.setAttribute("x", x);
        textEl.setAttribute("y", y);
        textEl.setAttribute("text-anchor", anchor || "middle");

        lines.forEach(function(line, idx) {
            var tspan = document.createElementNS("http://www.w3.org/2000/svg", "tspan");
            tspan.setAttribute("x", x);
            tspan.setAttribute("dy", (idx === 0 ? startDy : lineHeight));
            tspan.textContent = line;
            textEl.appendChild(tspan);
        });
    }

    function updateNodeVisual(nodeId, x, y) {
        if (!topologyRuntime || !topologyRuntime.layout) { return; }
        var entry = topologyRuntime.nodeRegistry[nodeId];
        if (!entry) { return; }

        var layout = topologyRuntime.layout;
        var nodeR = NODE_R;
        layout.positions[nodeId] = { x: x, y: y };

        if (entry.shapeTag === "circle") {
            entry.shape.setAttribute("cx", x);
            entry.shape.setAttribute("cy", y);
        } else if (entry.shapeTag === "image") {
            entry.shape.setAttribute("x", x - nodeR);
            entry.shape.setAttribute("y", y - nodeR);
        }

        if (entry.toggle) {
            entry.toggle.setAttribute("transform", "translate(" + (x + 16) + "," + (y - 12) + ")");
        }
        var labelPos = computeLabelPosition(x, y, nodeR);
        setMultilineText(entry.text, entry.node.label || "", labelPos.x, labelPos.y, LABEL_MAX_CHARS, LABEL_LINE_HEIGHT, labelPos.anchor);

        topologyRuntime.edgeRegistry.forEach(function(rec) {
            var edge = rec.edge;
            if (edge.source !== nodeId && edge.target !== nodeId) { return; }
            var sp = layout.positions[edge.source];
            var tp = layout.positions[edge.target];
            if (!sp || !tp) { return; }
            rec.line.setAttribute("x1", sp.x);
            rec.line.setAttribute("y1", sp.y);
            rec.line.setAttribute("x2", tp.x);
            rec.line.setAttribute("y2", tp.y);
            rec.hit.setAttribute("x1", sp.x);
            rec.hit.setAttribute("y1", sp.y);
            rec.hit.setAttribute("x2", tp.x);
            rec.hit.setAttribute("y2", tp.y);
        });
    }

    function startNodeDrag(nodeId, clientX, clientY) {
        if (!topologyRuntime || !topologyRuntime.layout) { return; }
        var pos = topologyRuntime.layout.positions[nodeId];
        if (!pos) { return; }

        nodeDragActive = true;
        nodeDragId = nodeId;
        nodeDragStartX = clientX;
        nodeDragStartY = clientY;
        nodeDragOrigX = pos.x;
        nodeDragOrigY = pos.y;
        dragActive = false;
        touchPanActive = false;

        var entry = topologyRuntime.nodeRegistry[nodeId];
        if (entry && entry.group) {
            entry.group.classList.add("lldp-node-dragging");
        }
        if (canvasWrap) {
            canvasWrap.classList.remove("lldp-dragging");
        }
        hideTooltip();
    }

    function moveNodeDrag(clientX, clientY) {
        if (!nodeDragActive || !nodeDragId) { return; }
        var dx = (clientX - nodeDragStartX) / currentZoom;
        var dy = (clientY - nodeDragStartY) / currentZoom;
        hasUserAdjustedView = true;
        updateNodeVisual(nodeDragId, nodeDragOrigX + dx, nodeDragOrigY + dy);
    }

    function endNodeDrag() {
        if (nodeDragId && topologyRuntime && topologyRuntime.nodeRegistry[nodeDragId]) {
            topologyRuntime.nodeRegistry[nodeDragId].group.classList.remove("lldp-node-dragging");
        }
        nodeDragActive = false;
        nodeDragId = null;
    }

    function startDrag(clientX, clientY) {
        if (!zoomRoot) { return; }
        dragActive = true;
        dragStartX = clientX;
        dragStartY = clientY;
        dragStartPanX = panX;
        dragStartPanY = panY;
        if (canvasWrap) { canvasWrap.classList.add("lldp-dragging"); }
        hideTooltip();
    }

    function moveDrag(clientX, clientY) {
        if (!dragActive) { return; }
        panX = dragStartPanX + (clientX - dragStartX) / currentZoom;
        panY = dragStartPanY + (clientY - dragStartY) / currentZoom;
        hasUserAdjustedView = true;
        renderTransform();
    }

    function endDrag() {
        dragActive = false;
        if (canvasWrap) { canvasWrap.classList.remove("lldp-dragging"); }
    }

    function dampPinchRatio(ratio) {
        return 1 + (ratio - 1) * PINCH_ZOOM_SENSITIVITY;
    }

    function bindPinchZoom() {
        if (!canvasWrap || canvasWrap.dataset.pinchBound === "1") { return; }
        canvasWrap.dataset.pinchBound = "1";

        canvasWrap.addEventListener("mousedown", function(e) {
            if (!zoomRoot || e.button !== 0) { return; }
            if (isZoomControlTarget(e.target)) { return; }
            if (isNodeTarget(e.target)) { return; }
            startDrag(e.clientX, e.clientY);
            e.preventDefault();
        });

        document.addEventListener("mousemove", function(e) {
            if (nodeDragActive) {
                moveNodeDrag(e.clientX, e.clientY);
            } else if (dragActive) {
                moveDrag(e.clientX, e.clientY);
            }
        });

        document.addEventListener("mouseup", function() {
            endNodeDrag();
            endDrag();
        });

        canvasWrap.addEventListener("touchstart", function(e) {
            if (!zoomRoot) { return; }
            if (e.touches.length === 2) {
                touchPanActive = false;
                pinchActive = true;
                pinchStartDistance = getTouchDistance(e.touches);
                pinchStartZoom = currentZoom;
                pinchStartPanX = panX;
                pinchStartPanY = panY;
                hideTooltip();
                return;
            }
            if (e.touches.length === 1) {
                if (isNodeTarget(e.target)) { return; }
                touchPanActive = true;
                touchPanLastX = e.touches[0].clientX;
                touchPanLastY = e.touches[0].clientY;
                hideTooltip();
            }
        }, { passive: true });

        canvasWrap.addEventListener("touchmove", function(e) {
            if (!zoomRoot) { return; }
            if (nodeDragActive && e.touches.length === 1) {
                e.preventDefault();
                moveNodeDrag(e.touches[0].clientX, e.touches[0].clientY);
                return;
            }
            if (pinchActive && e.touches.length === 2) {
                if (pinchStartDistance <= 0) { return; }
                e.preventDefault();
                var dist = getTouchDistance(e.touches);
                var center = getTouchCenter(e.touches);
                var ratio = dampPinchRatio(dist / pinchStartDistance);
                var targetZoom = pinchStartZoom * ratio;
                panX = pinchStartPanX;
                panY = pinchStartPanY;
                currentZoom = pinchStartZoom;
                applyZoom(targetZoom, center);
                return;
            }
            if (touchPanActive && e.touches.length === 1) {
                e.preventDefault();
                var dx = e.touches[0].clientX - touchPanLastX;
                var dy = e.touches[0].clientY - touchPanLastY;
                panX += dx / currentZoom;
                panY += dy / currentZoom;
                touchPanLastX = e.touches[0].clientX;
                touchPanLastY = e.touches[0].clientY;
                renderTransform();
            }
        }, { passive: false });

        canvasWrap.addEventListener("touchend", function(e) {
            if (e.touches.length < 2) {
                pinchActive = false;
                pinchStartDistance = 0;
            }
            if (e.touches.length === 0) {
                touchPanActive = false;
                endNodeDrag();
            }
        }, { passive: true });

        canvasWrap.addEventListener("touchcancel", function() {
            pinchActive = false;
            pinchStartDistance = 0;
            touchPanActive = false;
            endNodeDrag();
        }, { passive: true });

        canvasWrap.addEventListener("wheel", function(e) {
            if (!zoomRoot) { return; }
            if (!e.ctrlKey) { return; }
            e.preventDefault();
            hideTooltip();
            var factor = Math.exp(-e.deltaY * WHEEL_ZOOM_SENSITIVITY);
            factor = Math.max(0.92, Math.min(1.08, factor));
            applyZoom(currentZoom * factor, { clientX: e.clientX, clientY: e.clientY });
        }, { passive: false });
    }

' . file_get_contents(dirname(__DIR__) . '/assets/topology-layout.js') . '

    function createSvg(tag, attributes) {
        var element = document.createElementNS("http://www.w3.org/2000/svg", tag);
        Object.keys(attributes).forEach(function(key) { element.setAttribute(key, attributes[key]); });
        return element;
    }

    function renderRegions(regions) {
        var group = createSvg("g", { "aria-hidden": "true" });
        regions.forEach(function(region) {
            group.appendChild(createSvg("rect", {
                x: region.x, y: region.y, width: region.width, height: region.height,
                rx: 12, "class": "lldp-region"
            }));
            if (region.label) {
                var title = createSvg("text", {
                    x: region.x + 10, y: region.y + 17, "class": "lldp-region-label"
                });
                title.textContent = region.label.length > 23 ? region.label.slice(0, 22) + "…" : region.label;
                group.appendChild(title);
            }
        });
        return group;
    }

    function renderBranchToggle(group, node, x, y, badge) {
        var label = badge.open ? labels.collapseBranch : labels.expandBranch.replace("%d", badge.count);
        if (badge.severity >= 0) {
            label += " · " + labels.hiddenAlert + " · " + getSeverityLabel(badge.severity);
        }
        var toggle = createSvg("g", {
            "class": "lldp-toggle" + (badge.severity >= 0 ? " lldp-hidden-alert" : ""),
            transform: "translate(" + (x + 16) + "," + (y - 12) + ")",
            role: "button", tabindex: "0", "aria-label": label, "aria-expanded": badge.open
        });
        var title = createSvg("title", {});
        title.textContent = label;
        toggle.appendChild(title);
        toggle.appendChild(createSvg("rect", { width: 48, height: 24, rx: 6 }));
        var text = createSvg("text", { x: 24, y: 16, "text-anchor": "middle" });
        text.textContent = badge.open ? "−" : (badge.severity >= 0 ? "! " : "") + "+" + badge.count;
        toggle.appendChild(text);
        function activate(event) {
            event.stopPropagation();
            if (badge.open) { expandedNodes.delete(node.id); }
            else { expandedNodes.add(node.id); }
            renderTopology(topologyData, { preserveView: hasUserAdjustedView });
            var entry = topologyRuntime.nodeRegistry[node.id];
            if (entry && entry.toggle) { entry.toggle.focus(); }
        }
        toggle.addEventListener("click", activate);
        toggle.addEventListener("mousedown", function(event) { event.stopPropagation(); });
        toggle.addEventListener("touchstart", function(event) { event.stopPropagation(); }, { passive: true });
        toggle.addEventListener("keydown", function(event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                activate(event);
            }
        });
        group.appendChild(toggle);
        return toggle;
    }

    function renderNode(group, node, x, y, badge) {
        var kind = node.kind || "remote";
        var severityClass = getSeverityClass(node);
        group.setAttribute("class", "lldp-node " + kind + (severityClass ? (" " + severityClass) : ""));
        group.setAttribute("data-node-id", node.id);

        var title = document.createElementNS("http://www.w3.org/2000/svg", "title");
        title.textContent = node.label;
        group.appendChild(title);

        var shape;
        var shapeTag;
        if (node.image) {
            shape = document.createElementNS("http://www.w3.org/2000/svg", "image");
            shape.setAttribute("href", node.image);
            shape.setAttribute("x", x - NODE_R);
            shape.setAttribute("y", y - NODE_R);
            shape.setAttribute("width", NODE_R * 2);
            shape.setAttribute("height", NODE_R * 2);
            shapeTag = "image";
            group.appendChild(shape);
        } else {
            shape = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            shape.setAttribute("cx", x);
            shape.setAttribute("cy", y);
            shape.setAttribute("r", NODE_R);
            shapeTag = "circle";
            group.appendChild(shape);
        }

        var text = document.createElementNS("http://www.w3.org/2000/svg", "text");
        var labelPos = computeLabelPosition(x, y, NODE_R);
        setMultilineText(text, node.label || "", labelPos.x, labelPos.y, LABEL_MAX_CHARS, LABEL_LINE_HEIGHT, labelPos.anchor);
        group.appendChild(text);

        if (topologyRuntime) {
            topologyRuntime.nodeRegistry[node.id] = {
                group: group,
                shape: shape,
                shapeTag: shapeTag,
                text: text,
                node: node,
                toggle: badge ? renderBranchToggle(group, node, x, y, badge) : null
            };
        }

        group.addEventListener("mouseenter", function(e) {
            if (nodeDragActive) { return; }
            var kindLabel = kind === "local" ? labels.local : labels.remote;
            var tipHtml = (node.hostid)
                ? buildNodeTooltip(node, kindLabel)
                : ("<strong>" + escapeHtml(kindLabel) + "</strong><br>" + escapeHtml(node.label || ""));
            showTooltip(tipHtml, e);
        });
        group.addEventListener("mouseleave", hideTooltip);

        group.addEventListener("mousedown", function(e) {
            if (e.button !== 0) { return; }
            e.stopPropagation();
            startNodeDrag(node.id, e.clientX, e.clientY);
            e.preventDefault();
        });

        group.addEventListener("touchstart", function(e) {
            if (e.touches.length !== 1) { return; }
            e.stopPropagation();
            touchPanActive = false;
            startNodeDrag(node.id, e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: true });
    }

    function renderTopology(data, options) {
        if (!canvasWrap) { return; }

        options = options || {};
        var previousState = options.preserveView ? captureViewState() : null;

        var focusedNode = document.activeElement && document.activeElement.closest(".lldp-node");
        var focusedId = focusedNode && focusedNode.getAttribute("data-node-id");

        var svgOld = canvasWrap.querySelector("svg.lldp-svg");
        if (svgOld) { svgOld.remove(); }
        resetZoomState();
        if (placeholder) { placeholder.style.display = "none"; }

        var nodes = data.nodes || [];
        var edges = data.edges || [];
        var query = filterInput ? filterInput.value.trim().toLowerCase() : "";
        var visibility = getVisibleTopology(topologyGraph, edges, expandedNodes, query
            ? function(edge) { return edgeMatchesFilter(edge, query); } : null);
        nodes = nodes.filter(function(node) { return visibility.visible.has(node.id); });
        if (nodes.length === 0) {
            if (placeholder) {
                placeholder.textContent = labels.noData;
                placeholder.style.display = "flex";
            }
            return;
        }

        var layout = layoutTopology(topologyGraph, visibility.visible);
        layout.signature = JSON.stringify(layout.positions);
        if (previousState && previousState.signature === layout.signature) {
            applySavedNodePositions(layout, nodes, previousState);
        }
        topologyRuntime = {
            layout: layout,
            nodeRegistry: {},
            edgeRegistry: []
        };

        var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("class", "lldp-svg");
        var initialWidth = Math.max(layout.width, canvasWrap ? (canvasWrap.clientWidth || 0) : 0);
        var initialHeight = Math.max(layout.height, canvasWrap ? (canvasWrap.clientHeight || 0) : 0);
        svg.setAttribute("width", initialWidth);
        svg.setAttribute("height", initialHeight);
        svg.setAttribute("viewBox", "0 0 " + layout.width + " " + layout.height);

        var rootG = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        rootG.setAttribute("id", "lldp-zoom-root");
        rootG.setAttribute("x", "0");
        rootG.setAttribute("y", "0");
        rootG.setAttribute("width", initialWidth);
        rootG.setAttribute("height", initialHeight);
        rootG.setAttribute("viewBox", "0 0 " + layout.width + " " + layout.height);
        rootG.setAttribute("preserveAspectRatio", "xMidYMid meet");
        rootG.setAttribute("overflow", "visible");

        rootG.appendChild(renderRegions(layout.regions));
        var edgesG = document.createElementNS("http://www.w3.org/2000/svg", "g");
        edges.forEach(function(edge) {
            var sp = layout.positions[edge.source];
            var tp = layout.positions[edge.target];
            if (!sp || !tp) { return; }

            var hit = document.createElementNS("http://www.w3.org/2000/svg", "line");
            hit.setAttribute("class", "lldp-edge-hit");
            hit.setAttribute("x1", sp.x);
            hit.setAttribute("y1", sp.y);
            hit.setAttribute("x2", tp.x);
            hit.setAttribute("y2", tp.y);

            var line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            var graph = topologyGraph.graph;
            var primary = graph.get(edge.source).parent === edge.target
                || graph.get(edge.target).parent === edge.source;
            line.setAttribute("class", primary ? "lldp-edge" : "lldp-edge lldp-redundant");
            line.setAttribute("x1", sp.x);
            line.setAttribute("y1", sp.y);
            line.setAttribute("x2", tp.x);
            line.setAttribute("y2", tp.y);

            var tipHtml = "<strong>" + labels.localDevice + "</strong> " + escapeHtml(edge.localDevice || "") + "<br>"
                + "<strong>" + labels.localPort + "</strong> " + escapeHtml(edge.localPort || "") + "<br>"
                + "<strong>" + labels.remoteDevice + "</strong> " + escapeHtml(edge.remoteDevice || "") + "<br>"
                + "<strong>" + labels.remotePort + "</strong> " + escapeHtml(edge.remotePort || "") + "<br>"
                + "<strong>" + labels.lastUpdate + "</strong> " + formatTime(edge.lastclock);

            function bindHover(el) {
                el.addEventListener("mouseenter", function(e) {
                    line.classList.add("lldp-active");
                    showTooltip(tipHtml, e);
                });
                el.addEventListener("mouseleave", function() {
                    line.classList.remove("lldp-active");
                    hideTooltip();
                });
            }
            bindHover(hit);
            bindHover(line);

            topologyRuntime.edgeRegistry.push({
                edge: edge,
                line: line,
                hit: hit
            });

            edgesG.appendChild(hit);
            edgesG.appendChild(line);
        });
        rootG.appendChild(edgesG);

        var nodesG = document.createElementNS("http://www.w3.org/2000/svg", "g");
        nodes.forEach(function(node) {
            var pos = layout.positions[node.id];
            if (!pos) { return; }
            var g = document.createElementNS("http://www.w3.org/2000/svg", "g");
            renderNode(g, node, pos.x, pos.y, visibility.badges.get(node.id));
            nodesG.appendChild(g);
        });
        rootG.appendChild(nodesG);
        svg.appendChild(rootG);

        canvasWrap.appendChild(svg);
        var focusedEntry = focusedId && topologyRuntime.nodeRegistry[focusedId];
        if (focusedEntry && focusedEntry.toggle) { focusedEntry.toggle.focus(); }

        zoomSvg = svg;
        zoomRoot = rootG;
        zoomLayout = layout;

        if (previousState) {
            currentZoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, Number(previousState.zoom) || 1));
            panX = Number(previousState.panX) || 0;
            panY = Number(previousState.panY) || 0;
        } else {
            currentZoom = getFitZoom(layout);
            panX = 0;
            panY = 0;
        }

        renderTransform();
        setPinchable(true);
        setZoomControlsEnabled(true);
        applyEdgeFilter();
    }

    function runQuery(options) {
        if (!queryBtn) { return; }
        if (queryBtn.disabled) { return; }
        if (isQuerying) { return; }

        options = options || {};
        var silent = !!options.silent;
        var preserveView = !!options.preserveView;
        isQuerying = true;

        var groupid = document.getElementById("lldp-group-select");
        var hostid = document.getElementById("lldp-host-select");
        var params = new URLSearchParams();
        params.append("groupid", groupid ? groupid.value : "0");
        params.append("hostid", hostid ? hostid.value : "0");

        var scope = params.toString();
        var originalText = queryBtn.textContent;
        if (!silent) {
            queryBtn.disabled = true;
            queryBtn.textContent = labels.querying;
            if (statsEl) { statsEl.textContent = ""; }
        }

        fetch("zabbix.php?action=lldp.data", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: params.toString()
        })
            .then(function(resp) { return resp.json(); })
            .then(function(result) {
                isQuerying = false;
                if (!silent) {
                    queryBtn.disabled = false;
                    queryBtn.textContent = originalText;
                }

                if (result && result.ok && result.data) {
                    var sameScope = scope === queryScope;
                    if (!sameScope) {
                        expandedNodes.clear();
                        hasUserAdjustedView = false;
                    }
                    queryScope = scope;
                    topologyData = result.data;
                    topologyGraph = buildTopologyGraph(result.data.nodes, result.data.edges,
                        params.get("hostid") !== "0" ? "host:" + params.get("hostid") : result.data.preferredRoot);
                    expandedNodes.forEach(function(id) {
                        if (!topologyGraph.graph.has(id)) { expandedNodes.delete(id); }
                    });
                    if (statsEl) {
                        var txt = formatStats(result.data.stats);
                        if (result.data.stats && result.data.stats.skipped) {
                            txt += " (" + labels.skipped + ": " + result.data.stats.skipped + ")";
                        }
                        baseStatsText = txt;
                        statsEl.textContent = txt;
                    }
                    renderTopology(topologyData, { preserveView: sameScope && preserveView });
                } else {
                    topologyData = null;
                    topologyGraph = null;
                    if (placeholder) {
                        placeholder.textContent = (result && result.message) ? result.message : labels.noData;
                        placeholder.style.display = "flex";
                    }
                    var svgOld = canvasWrap.querySelector("svg.lldp-svg");
                    if (svgOld) { svgOld.remove(); }
                    resetZoomState();
                    if (!silent) {
                        alert((result && result.message) ? result.message : labels.queryFailed);
                    }
                }
            })
            .catch(function(err) {
                isQuerying = false;
                if (!silent) {
                    queryBtn.disabled = false;
                    queryBtn.textContent = originalText;
                    alert(labels.queryFailed + ": " + String(err));
                }
            });
    }

    function startAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
        }
        autoRefreshTimer = setInterval(function() {
            if (document.hidden) { return; }
            if (dragActive || nodeDragActive || pinchActive) { return; }
            runQuery({ silent: true, preserveView: hasUserAdjustedView });
        }, AUTO_REFRESH_MS);
    }

    function stopAutoRefresh() {
        if (!autoRefreshTimer) { return; }
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }

    if (queryBtn) {
        queryBtn.addEventListener("click", runQuery);
    }

    if (filterInput) {
        filterInput.addEventListener("input", function() {
            if (topologyData) { renderTopology(topologyData, { preserveView: hasUserAdjustedView }); }
        });
    }

    if (filterClearBtn) {
        filterClearBtn.addEventListener("click", function() {
            if (filterInput) {
                filterInput.value = "";
            }
            if (topologyData) { renderTopology(topologyData, { preserveView: hasUserAdjustedView }); }
        });
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener("click", function() {
            hasUserAdjustedView = true;
            applyZoom(currentZoom * ZOOM_STEP);
        });
    }

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener("click", function() {
            hasUserAdjustedView = true;
            applyZoom(currentZoom / ZOOM_STEP);
        });
    }

    bindPinchZoom();
    startAutoRefresh();

    document.addEventListener("visibilitychange", function() {
        if (!document.hidden) {
            runQuery({ silent: true, preserveView: hasUserAdjustedView });
        }
    });

    window.addEventListener("beforeunload", function() {
        stopAutoRefresh();
    });
})();
</script>'));

ViewRenderer::render($pageTitle, $styleTag, $content);
