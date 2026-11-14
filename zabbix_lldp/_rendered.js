(function() {
    "use strict";

    var labels = {"querying":"Querying...","query":"Query","queryFailed":"Query failed","statsFormat":"%d hosts \/ %d links \/ %d devices","placeholder":"Select filters and click Query to load topology","noData":"No LLDP data found","localDevice":"Local device","remoteDevice":"Remote device","localPort":"Local port","remotePort":"Remote port","lastUpdate":"Last update","local":"Local","remote":"Remote","skipped":"Skipped items","zoomIn":"Zoom in","zoomOut":"Zoom out","clickExpand":"Click to expand next layer","hiddenNeighbors":"Hidden neighbors","inferredLink":"Inferred link","topologyGroup":"Topology","layersShown":"Top 3 layers shown by default; click or double-click +N nodes to expand","dragNode":"Drag to move node","moreRemotes":"More remotes"};
    var NODE_R = 10;
    var LABEL_PAD = 56;
    var ZOOM_MIN = 0.5;
    var ZOOM_MAX = 2.5;
    var ZOOM_STEP = 1.1;
    var WHEEL_ZOOM_SENSITIVITY = 0.00065;
    var PINCH_ZOOM_SENSITIVITY = 0.55;
    var DEFAULT_MAX_LAYER = 2;
    var COMPONENT_GAP = 64;
    var DRAG_CLICK_THRESHOLD = 5;
    var MAX_REMOTES_PER_LOCAL = 48;
    var NODES_PER_RING = 12;
    var MIN_ARC_GAP = 34;

    var queryBtn = document.getElementById("lldp-query-btn");
    var statsEl = document.getElementById("lldp-stats");
    var canvasWrap = document.getElementById("lldp-canvas-wrap");
    var placeholder = document.getElementById("lldp-placeholder");
    var layerHint = document.getElementById("lldp-layer-hint");
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
    var fullTopologyData = null;
    var topologyState = { revealed: {}, expandedLocals: {}, nodePositions: {}, dom: { nodes: {}, edges: [] } };
    var dragMoved = false;
    var pendingExpandNodeId = null;
    var touchTapMoved = false;
    var touchExpandNodeId = null;
    var nodeDragActive = false;
    var nodeDragId = null;
    var nodeDragOffsetX = 0;
    var nodeDragOffsetY = 0;

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
        zoomSvg.setAttribute("width", zoomLayout.width * currentZoom);
        zoomSvg.setAttribute("height", zoomLayout.height * currentZoom);
        updateZoomButtons();
    }

    function clientToViewBox(clientX, clientY) {
        if (!canvasWrap || !zoomLayout) { return { x: 0, y: 0 }; }
        var rect = canvasWrap.getBoundingClientRect();
        var cssX = clientX - rect.left + canvasWrap.scrollLeft;
        var cssY = clientY - rect.top + canvasWrap.scrollTop;
        var cx = zoomLayout.cx;
        var cy = zoomLayout.cy;
        var z = currentZoom;
        var vx = cssX / z;
        var vy = cssY / z;
        return {
            x: cx + (vx - panX - cx) / z,
            y: cy + (vy - panY - cy) / z
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

    function getMinCanvasSize() {
        if (!canvasWrap) {
            return { width: 960, height: 560 };
        }
        var w = canvasWrap.clientWidth || 960;
        var h = canvasWrap.clientHeight || 560;
        return { width: Math.max(w, 640), height: Math.max(h, 420) };
    }

    function getNodePosition(nodeId) {
        if (topologyState.nodePositions[nodeId]) {
            return topologyState.nodePositions[nodeId];
        }
        if (zoomLayout && zoomLayout.positions && zoomLayout.positions[nodeId]) {
            return zoomLayout.positions[nodeId];
        }
        return { x: 0, y: 0 };
    }

    function syncNodeAndEdges(nodeId) {
        var pos = getNodePosition(nodeId);
        var nodeDom = topologyState.dom.nodes[nodeId];
        if (nodeDom) {
            var r = nodeDom.nodeR || NODE_R;
            nodeDom.circle.setAttribute("cx", pos.x);
            nodeDom.circle.setAttribute("cy", pos.y);
            if (nodeDom.text) {
                nodeDom.text.setAttribute("x", pos.x);
                nodeDom.text.setAttribute("y", pos.y + r + 12);
            }
            if (nodeDom.badge) {
                nodeDom.badge.setAttribute("x", pos.x);
                nodeDom.badge.setAttribute("y", pos.y - r - 4);
            }
        }
        topologyState.dom.edges.forEach(function(ed) {
            if (ed.source !== nodeId && ed.target !== nodeId) { return; }
            var sp = getNodePosition(ed.source);
            var tp = getNodePosition(ed.target);
            ed.line.setAttribute("x1", sp.x);
            ed.line.setAttribute("y1", sp.y);
            ed.line.setAttribute("x2", tp.x);
            ed.line.setAttribute("y2", tp.y);
            ed.hit.setAttribute("x1", sp.x);
            ed.hit.setAttribute("y1", sp.y);
            ed.hit.setAttribute("x2", tp.x);
            ed.hit.setAttribute("y2", tp.y);
        });
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
        nodeDragActive = false;
        nodeDragId = null;
        touchPanActive = false;
        topologyState.dom = { nodes: {}, edges: [] };
        if (canvasWrap) {
            canvasWrap.classList.remove("lldp-dragging");
            canvasWrap.classList.remove("lldp-node-dragging");
        }
        zoomRoot = null;
        zoomLayout = null;
        zoomSvg = null;
        setPinchable(false);
        setZoomControlsEnabled(false);
    }

    function resetTopologyState() {
        topologyState.revealed = {};
        topologyState.expandedLocals = {};
        topologyState.nodePositions = {};
        topologyState.dom = { nodes: {}, edges: [] };
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

    function startDrag(clientX, clientY) {
        if (!zoomRoot) { return; }
        dragActive = true;
        dragMoved = false;
        dragStartX = clientX;
        dragStartY = clientY;
        dragStartPanX = panX;
        dragStartPanY = panY;
        if (canvasWrap) { canvasWrap.classList.add("lldp-dragging"); }
        hideTooltip();
    }

    function moveDrag(clientX, clientY) {
        if (!dragActive) { return; }
        if (Math.abs(clientX - dragStartX) > DRAG_CLICK_THRESHOLD
            || Math.abs(clientY - dragStartY) > DRAG_CLICK_THRESHOLD) {
            dragMoved = true;
        }
        panX = dragStartPanX + (clientX - dragStartX) / currentZoom;
        panY = dragStartPanY + (clientY - dragStartY) / currentZoom;
        renderTransform();
    }

    function endDrag() {
        if (nodeDragActive) {
            nodeDragActive = false;
            if (topologyState.dom.nodes[nodeDragId]) {
                topologyState.dom.nodes[nodeDragId].group.classList.remove("dragging");
            }
            if (!dragMoved && pendingExpandNodeId) {
                expandNodeNeighbors(pendingExpandNodeId);
            }
            nodeDragId = null;
            if (canvasWrap) { canvasWrap.classList.remove("lldp-node-dragging"); }
            pendingExpandNodeId = null;
            dragActive = false;
            return;
        }
        if (!dragMoved && pendingExpandNodeId) {
            expandNodeNeighbors(pendingExpandNodeId);
        }
        pendingExpandNodeId = null;
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
            pendingExpandNodeId = null;
            dragMoved = false;
            var nodeEl = e.target.closest ? e.target.closest(".lldp-node") : null;
            if (nodeEl && nodeEl.getAttribute) {
                nodeDragId = nodeEl.getAttribute("data-node-id");
                pendingExpandNodeId = nodeDragId;
                var pt = clientToViewBox(e.clientX, e.clientY);
                var npos = getNodePosition(nodeDragId);
                nodeDragOffsetX = pt.x - npos.x;
                nodeDragOffsetY = pt.y - npos.y;
                nodeDragActive = true;
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                nodeEl.classList.add("dragging");
                if (canvasWrap) { canvasWrap.classList.add("lldp-node-dragging"); }
                hideTooltip();
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            startDrag(e.clientX, e.clientY);
            e.preventDefault();
        });

        document.addEventListener("mousemove", function(e) {
            if (nodeDragActive && nodeDragId) {
                if (Math.abs(e.clientX - dragStartX) > DRAG_CLICK_THRESHOLD
                    || Math.abs(e.clientY - dragStartY) > DRAG_CLICK_THRESHOLD) {
                    dragMoved = true;
                }
                var pt = clientToViewBox(e.clientX, e.clientY);
                topologyState.nodePositions[nodeDragId] = {
                    x: pt.x - nodeDragOffsetX,
                    y: pt.y - nodeDragOffsetY
                };
                syncNodeAndEdges(nodeDragId);
                return;
            }
            if (dragActive) {
                moveDrag(e.clientX, e.clientY);
            }
        });

        document.addEventListener("mouseup", function() {
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
                touchPanActive = false;
                touchTapMoved = false;
                touchExpandNodeId = null;
                nodeDragActive = false;
                nodeDragId = null;
                dragMoved = false;
                var nodeEl = e.target.closest ? e.target.closest(".lldp-node") : null;
                if (nodeEl && nodeEl.getAttribute) {
                    nodeDragId = nodeEl.getAttribute("data-node-id");
                    touchExpandNodeId = nodeDragId;
                    pendingExpandNodeId = nodeDragId;
                    var pt = clientToViewBox(e.touches[0].clientX, e.touches[0].clientY);
                    var npos = getNodePosition(nodeDragId);
                    nodeDragOffsetX = pt.x - npos.x;
                    nodeDragOffsetY = pt.y - npos.y;
                    nodeDragActive = true;
                    dragStartX = e.touches[0].clientX;
                    dragStartY = e.touches[0].clientY;
                    nodeEl.classList.add("dragging");
                    if (canvasWrap) { canvasWrap.classList.add("lldp-node-dragging"); }
                } else {
                    touchPanActive = true;
                    touchPanLastX = e.touches[0].clientX;
                    touchPanLastY = e.touches[0].clientY;
                }
                hideTooltip();
            }
        }, { passive: true });

        canvasWrap.addEventListener("touchmove", function(e) {
            if (!zoomRoot) { return; }
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
            if (nodeDragActive && nodeDragId && e.touches.length === 1) {
                e.preventDefault();
                if (Math.abs(e.touches[0].clientX - dragStartX) > DRAG_CLICK_THRESHOLD
                    || Math.abs(e.touches[0].clientY - dragStartY) > DRAG_CLICK_THRESHOLD) {
                    dragMoved = true;
                    touchTapMoved = true;
                }
                var pt = clientToViewBox(e.touches[0].clientX, e.touches[0].clientY);
                topologyState.nodePositions[nodeDragId] = {
                    x: pt.x - nodeDragOffsetX,
                    y: pt.y - nodeDragOffsetY
                };
                syncNodeAndEdges(nodeDragId);
                return;
            }
            if (touchPanActive && e.touches.length === 1) {
                e.preventDefault();
                var dx = e.touches[0].clientX - touchPanLastX;
                var dy = e.touches[0].clientY - touchPanLastY;
                if (Math.abs(dx) > DRAG_CLICK_THRESHOLD || Math.abs(dy) > DRAG_CLICK_THRESHOLD) {
                    touchTapMoved = true;
                }
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
                var expandId = touchExpandNodeId;
                if (nodeDragActive) {
                    if (topologyState.dom.nodes[nodeDragId]) {
                        topologyState.dom.nodes[nodeDragId].group.classList.remove("dragging");
                    }
                    if (!dragMoved && expandId) {
                        expandNodeNeighbors(expandId);
                        expandId = null;
                    }
                    nodeDragActive = false;
                    nodeDragId = null;
                    pendingExpandNodeId = null;
                    if (canvasWrap) { canvasWrap.classList.remove("lldp-node-dragging"); }
                }
                touchPanActive = false;
                if (!touchTapMoved && expandId) {
                    expandNodeNeighbors(expandId);
                }
                touchExpandNodeId = null;
                dragMoved = false;
            }
        }, { passive: true });

        canvasWrap.addEventListener("touchcancel", function() {
            pinchActive = false;
            pinchStartDistance = 0;
            touchPanActive = false;
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

    function prepareGraph(data) {
        var nodes = data.nodes || [];
        var edges = data.edges || [];
        var nodeById = {};
        nodes.forEach(function(n) { nodeById[n.id] = n; });

        var adj = {};
        nodes.forEach(function(n) { adj[n.id] = []; });
        edges.forEach(function(e) {
            if (!adj[e.source]) { adj[e.source] = []; }
            if (!adj[e.target]) { adj[e.target] = []; }
            if (adj[e.source].indexOf(e.target) === -1) { adj[e.source].push(e.target); }
            if (adj[e.target].indexOf(e.source) === -1) { adj[e.target].push(e.source); }
        });

        var layerMap = {};
        var queue = [];
        nodes.forEach(function(n) {
            if (n.kind === "local") {
                layerMap[n.id] = 0;
                queue.push(n.id);
            }
        });
        if (queue.length === 0 && nodes.length > 0) {
            layerMap[nodes[0].id] = 0;
            queue.push(nodes[0].id);
        }
        while (queue.length > 0) {
            var cur = queue.shift();
            var curLayer = layerMap[cur];
            (adj[cur] || []).forEach(function(nid) {
                if (layerMap[nid] === undefined) {
                    layerMap[nid] = curLayer + 1;
                    queue.push(nid);
                }
            });
        }
        nodes.forEach(function(n) {
            if (layerMap[n.id] === undefined) {
                layerMap[n.id] = 1;
            }
        });

        return { nodes: nodes, edges: edges, nodeById: nodeById, adj: adj, layerMap: layerMap };
    }

    function isNodeVisible(nodeId, graph) {
        var layer = graph.layerMap[nodeId];
        if (layer === undefined) { return false; }
        if (layer <= DEFAULT_MAX_LAYER) { return true; }
        return !!topologyState.revealed[nodeId];
    }

    function getLocalRemoteIds(localId, graph) {
        var list = [];
        (graph.adj[localId] || []).forEach(function(nid) {
            var node = graph.nodeById[nid];
            if (node && node.kind !== "local" && (graph.layerMap[nid] || 0) === 1) {
                list.push(nid);
            }
        });
        list.sort();
        return list;
    }

    function isRemoteAllowedForLocal(localId, remoteId, graph) {
        if (topologyState.expandedLocals[localId]) { return true; }
        var remotes = getLocalRemoteIds(localId, graph);
        if (remotes.length <= MAX_REMOTES_PER_LOCAL) { return true; }
        return remotes.indexOf(remoteId) < MAX_REMOTES_PER_LOCAL;
    }

    function computeVisibleInViewSet(graph) {
        var visible = {};
        var queue = [];
        graph.nodes.forEach(function(n) {
            if (n.kind === "local" && isNodeVisible(n.id, graph)) {
                visible[n.id] = true;
                queue.push(n.id);
            }
        });
        while (queue.length > 0) {
            var cur = queue.shift();
            (graph.adj[cur] || []).forEach(function(nid) {
                if (visible[nid] || !isNodeVisible(nid, graph)) { return; }
                var curNode = graph.nodeById[cur];
                if (curNode && curNode.kind === "local" && !isRemoteAllowedForLocal(cur, nid, graph)) {
                    return;
                }
                visible[nid] = true;
                queue.push(nid);
            });
        }
        return visible;
    }

    function isNodeVisibleInView(nodeId, graph) {
        if (graph.visibleInView) {
            return !!graph.visibleInView[nodeId];
        }
        return !!computeVisibleInViewSet(graph)[nodeId];
    }

    function countHiddenNeighbors(nodeId, graph) {
        var count = 0;
        (graph.adj[nodeId] || []).forEach(function(nid) {
            if (!isNodeVisibleInView(nid, graph)) { count++; }
        });
        var node = graph.nodeById[nodeId];
        if (node && node.kind === "local" && !topologyState.expandedLocals[nodeId]) {
            var remotes = getLocalRemoteIds(nodeId, graph);
            if (remotes.length > MAX_REMOTES_PER_LOCAL) {
                count += remotes.length - MAX_REMOTES_PER_LOCAL;
            }
        }
        return count;
    }

    function expandNodeNeighbors(nodeId) {
        if (!fullTopologyData) { return; }
        var graph = prepareGraph(fullTopologyData);
        var changed = false;
        var node = graph.nodeById[nodeId];
        if (node && node.kind === "local" && !topologyState.expandedLocals[nodeId]) {
            topologyState.expandedLocals[nodeId] = true;
            changed = true;
        }
        var queue = [nodeId];
        var seen = {};
        seen[nodeId] = true;
        while (queue.length > 0) {
            var cur = queue.shift();
            (graph.adj[cur] || []).forEach(function(nid) {
                if (seen[nid]) { return; }
                seen[nid] = true;
                if ((graph.layerMap[nid] || 0) > DEFAULT_MAX_LAYER && !topologyState.revealed[nid]) {
                    topologyState.revealed[nid] = true;
                    changed = true;
                }
                if ((graph.layerMap[nid] || 0) > DEFAULT_MAX_LAYER) {
                    queue.push(nid);
                }
            });
        }
        if (changed) {
            topologyState.nodePositions = {};
            renderTopology(fullTopologyData, true);
        }
    }

    function findComponents(graph) {
        var visited = {};
        var components = [];
        graph.nodes.forEach(function(start) {
            if (visited[start.id]) { return; }
            var compNodes = [];
            var compNodeIds = {};
            var queue = [start.id];
            visited[start.id] = true;
            while (queue.length > 0) {
                var id = queue.shift();
                compNodeIds[id] = true;
                compNodes.push(graph.nodeById[id]);
                (graph.adj[id] || []).forEach(function(nid) {
                    if (!visited[nid]) {
                        visited[nid] = true;
                        queue.push(nid);
                    }
                });
            }
            var compEdges = graph.edges.filter(function(e) {
                return compNodeIds[e.source] && compNodeIds[e.target];
            });
            components.push({ nodes: compNodes, edges: compEdges, nodeIds: compNodeIds });
        });
        return components;
    }

    function buildLocalChildren(nodes, edges) {
        var nodeById = {};
        nodes.forEach(function(n) { nodeById[n.id] = n; });
        var children = {};
        nodes.filter(function(n) { return n.kind === "local"; }).forEach(function(l) {
            children[l.id] = [];
        });
        edges.forEach(function(e) {
            if (!children[e.source]) { return; }
            var target = nodeById[e.target];
            if (!target || target.kind === "local") { return; }
            if (children[e.source].indexOf(target.id) === -1) {
                children[e.source].push(target.id);
            }
        });
        return children;
    }

    function ringRadiusForCount(n, minArcGap) {
        minArcGap = minArcGap || MIN_ARC_GAP;
        if (n <= 1) { return 70; }
        return Math.max(70, (n * minArcGap) / (2 * Math.PI));
    }

    function normalizeLayoutBounds(positions, labelPad, nodeR) {
        labelPad = labelPad || LABEL_PAD;
        nodeR = nodeR || NODE_R;
        var pad = labelPad + nodeR + 8;
        var minX = Infinity;
        var minY = Infinity;
        var maxX = -Infinity;
        var maxY = -Infinity;
        var ids = Object.keys(positions);
        if (ids.length === 0) {
            return { positions: positions, width: 320, height: 280, cx: 160, cy: 140 };
        }
        ids.forEach(function(id) {
            var p = positions[id];
            minX = Math.min(minX, p.x);
            minY = Math.min(minY, p.y);
            maxX = Math.max(maxX, p.x);
            maxY = Math.max(maxY, p.y);
        });
        var shiftX = pad - minX;
        var shiftY = pad - minY;
        ids.forEach(function(id) {
            positions[id].x += shiftX;
            positions[id].y += shiftY;
        });
        var width = Math.max(maxX - minX + pad * 2, 280);
        var height = Math.max(maxY - minY + pad * 2, 240);
        return { width: width, height: height, cx: width / 2, cy: height / 2 };
    }

    function layoutComponent(nodes, edges, fullLocalChildren) {
        var count = nodes.length;
        var nodeR = count > 50 ? 5 : (count > 30 ? 6 : (count > 18 ? 7 : NODE_R));
        var showLabels = count <= 28;
        var labelPad = showLabels ? LABEL_PAD : 42;
        fullLocalChildren = fullLocalChildren || buildLocalChildren(nodes, edges);

        if (count === 0) {
            return { positions: {}, width: 320, height: 280, cx: 160, cy: 140, nodeR: nodeR, showLabels: showLabels };
        }

        var locals = nodes.filter(function(n) { return n.kind === "local"; });
        var positions = {};
        var localChildren = {};
        locals.forEach(function(l) {
            var allKids = fullLocalChildren[l.id] || [];
            localChildren[l.id] = allKids.filter(function(cid) {
                return nodes.some(function(n) { return n.id === cid; });
            });
        });
        var assigned = {};

        function placeOnRing(ids, cx, cy, radius, startAngle, span) {
            if (ids.length === 0) { return; }
            ids.forEach(function(id, j) {
                if (assigned[id]) { return; }
                var t = ids.length === 1 ? 0.5 : j / (ids.length - 1);
                var angle = startAngle + span * t;
                positions[id] = {
                    x: cx + radius * Math.cos(angle),
                    y: cy + radius * Math.sin(angle)
                };
                assigned[id] = true;
            });
        }

        function placeFanFromLocal(lx, ly, childIds, baseAngle, fanSpread, fullCount) {
            if (childIds.length === 0) { return; }
            fullCount = fullCount || childIds.length;
            var totalForRadius = Math.max(childIds.length, fullCount);
            var perRing = Math.max(4, Math.min(NODES_PER_RING, Math.ceil(NODES_PER_RING * fanSpread / (2 * Math.PI))));
            var ringStep = Math.max(58, ringRadiusForCount(Math.min(perRing, childIds.length), MIN_ARC_GAP) * 0.75);
            var baseDist = Math.max(72, ringRadiusForCount(Math.min(perRing, totalForRadius), MIN_ARC_GAP));

            childIds.forEach(function(cid, j) {
                if (assigned[cid]) { return; }
                var ring = Math.floor(j / perRing);
                var idxInRing = j % perRing;
                var ringSize = Math.min(perRing, childIds.length - ring * perRing);
                var dist = baseDist + ring * ringStep;
                var t = ringSize === 1 ? 0.5 : idxInRing / (ringSize - 1);
                var a = baseAngle - fanSpread / 2 + fanSpread * t;
                positions[cid] = {
                    x: lx + dist * Math.cos(a),
                    y: ly + dist * Math.sin(a)
                };
                assigned[cid] = true;
            });
        }

        function placeOnRings(ids, cx, cy, baseRadius, ringStep) {
            var idx = 0;
            var ring = 0;
            while (idx < ids.length) {
                var chunk = ids.slice(idx, idx + NODES_PER_RING);
                var radius = baseRadius + ring * ringStep;
                placeOnRing(chunk, cx, cy, radius, -Math.PI / 2, 2 * Math.PI);
                idx += NODES_PER_RING;
                ring += 1;
            }
        }

        if (locals.length === 1) {
            var center = locals[0];
            var childIds = localChildren[center.id] || [];
            var fullChildCount = (fullLocalChildren[center.id] || []).length;
            var ringStep = Math.max(62, ringRadiusForCount(Math.min(NODES_PER_RING, Math.max(childIds.length, fullChildCount)), MIN_ARC_GAP) * 0.8);
            var baseR = ringRadiusForCount(Math.min(NODES_PER_RING, Math.max(childIds.length, fullChildCount)), MIN_ARC_GAP);
            var ringCount = Math.max(1, Math.ceil(childIds.length / NODES_PER_RING));
            var outerR = baseR + (ringCount - 1) * ringStep;
            var cx = outerR + labelPad;
            var cy = outerR + labelPad;
            positions[center.id] = { x: cx, y: cy };
            assigned[center.id] = true;
            placeOnRings(childIds, cx, cy, baseR, ringStep);
            nodes.forEach(function(n) {
                if (!assigned[n.id]) {
                    placeOnRing([n.id], cx, cy, outerR + ringStep * 0.6, -Math.PI / 2, 2 * Math.PI);
                }
            });
            var bounds = normalizeLayoutBounds(positions, labelPad, nodeR);
            return Object.assign({ positions: positions, nodeR: nodeR, showLabels: showLabels }, bounds);
        }

        if (locals.length > 1) {
            var localCount = locals.length;
            var maxChildCount = 0;
            locals.forEach(function(l) {
                maxChildCount = Math.max(maxChildCount, (localChildren[l.id] || []).length);
            });
            var innerR = Math.max(80, ringRadiusForCount(localCount, MIN_ARC_GAP + 8));
            var outerReach = Math.max(100, ringRadiusForCount(Math.min(NODES_PER_RING, maxChildCount), MIN_ARC_GAP) + 60);
            var cx = innerR + outerReach + labelPad;
            var cy = innerR + outerReach + labelPad;
            var sector = (2 * Math.PI) / localCount;

            locals.forEach(function(local, i) {
                var angle = i * sector - Math.PI / 2;
                var lx = cx + innerR * Math.cos(angle);
                var ly = cy + innerR * Math.sin(angle);
                positions[local.id] = { x: lx, y: ly };
                assigned[local.id] = true;
                var childIds = localChildren[local.id] || [];
                var fanSpread = Math.min(sector * 0.92, Math.PI * 1.6);
                var fullCount = (fullLocalChildren[local.id] || []).length;
                placeFanFromLocal(lx, ly, childIds, angle, fanSpread, fullCount);
            });

            var orphans = nodes.filter(function(n) { return !assigned[n.id]; });
            if (orphans.length > 0) {
                var orphanR = innerR + outerReach + 50;
                placeOnRing(orphans.map(function(n) { return n.id; }), cx, cy, orphanR, -Math.PI / 2, 2 * Math.PI);
            }

            var bounds = normalizeLayoutBounds(positions, labelPad, nodeR);
            return Object.assign({ positions: positions, nodeR: nodeR, showLabels: showLabels }, bounds);
        }

        var ordered = orderNodesForCircle(nodes, edges);
        var radius = ringRadiusForCount(count, MIN_ARC_GAP + 4);
        var cx = radius + labelPad;
        var cy = radius + labelPad;
        ordered.forEach(function(n, i) {
            var angle = (2 * Math.PI * i / count) - Math.PI / 2;
            positions[n.id] = {
                x: cx + radius * Math.cos(angle),
                y: cy + radius * Math.sin(angle)
            };
        });
        var bounds = normalizeLayoutBounds(positions, labelPad, nodeR);
        return Object.assign({ positions: positions, nodeR: nodeR, showLabels: showLabels }, bounds);
    }

    function layoutComponentsHorizontally(components, graph) {
        graph.visibleInView = computeVisibleInViewSet(graph);

        var offsetX = 20;
        var maxHeight = 0;
        var contentWidth = 40;
        var allPositions = {};
        var compLayouts = [];

        components.forEach(function(comp, idx) {
            var fullLocalChildren = buildLocalChildren(comp.nodes, comp.edges);
            var visibleNodes = comp.nodes.filter(function(n) {
                return isNodeVisibleInView(n.id, graph);
            });
            var visibleIds = {};
            visibleNodes.forEach(function(n) { visibleIds[n.id] = true; });
            var visibleEdges = comp.edges.filter(function(e) {
                return visibleIds[e.source] && visibleIds[e.target];
            });

            var layout = layoutComponent(visibleNodes, visibleEdges, fullLocalChildren);
            compLayouts.push({ layout: layout, index: idx, visibleNodes: visibleNodes, visibleEdges: visibleEdges });

            Object.keys(layout.positions).forEach(function(id) {
                allPositions[id] = {
                    x: layout.positions[id].x + offsetX,
                    y: layout.positions[id].y + 20
                };
            });

            offsetX += layout.width + COMPONENT_GAP;
            contentWidth = offsetX;
            maxHeight = Math.max(maxHeight, layout.height + 40);
        });

        var minCanvas = getMinCanvasSize();
        var totalWidth = Math.max(contentWidth, minCanvas.width);
        var totalHeight = Math.max(maxHeight, minCanvas.height);

        if (totalWidth > contentWidth) {
            var shiftX = (totalWidth - contentWidth) / 2;
            Object.keys(allPositions).forEach(function(id) {
                allPositions[id].x += shiftX;
            });
            compLayouts.forEach(function(ci) {
                ci.layout.cx += shiftX;
            });
        }
        if (totalHeight > maxHeight) {
            var shiftY = (totalHeight - maxHeight) / 2;
            Object.keys(allPositions).forEach(function(id) {
                allPositions[id].y += shiftY;
            });
        }

        return {
            positions: allPositions,
            width: totalWidth,
            height: totalHeight,
            cx: totalWidth / 2,
            cy: totalHeight / 2,
            compLayouts: compLayouts
        };
    }

    function buildAdjacency(nodes, edges) {
        var adj = {};
        nodes.forEach(function(n) { adj[n.id] = []; });
        (edges || []).forEach(function(e) {
            if (adj[e.source] && adj[e.source].indexOf(e.target) === -1) {
                adj[e.source].push(e.target);
            }
            if (adj[e.target] && adj[e.target].indexOf(e.source) === -1) {
                adj[e.target].push(e.source);
            }
        });
        return adj;
    }

    function orderNodesForCircle(nodes, edges) {
        if (nodes.length <= 1) { return nodes.slice(); }

        var adj = buildAdjacency(nodes, edges);
        var byId = {};
        nodes.forEach(function(n) { byId[n.id] = n; });

        var start = nodes[0];
        nodes.forEach(function(n) {
            var deg = (adj[n.id] || []).length;
            var startDeg = (adj[start.id] || []).length;
            if (deg > startDeg || (deg === startDeg && n.kind === "local" && start.kind !== "local")) {
                start = n;
            }
        });

        var ordered = [];
        var seen = {};
        function visit(id) {
            if (seen[id] || !byId[id]) { return; }
            seen[id] = true;
            ordered.push(byId[id]);
            (adj[id] || []).slice().sort(function(a, b) {
                return (adj[b] || []).length - (adj[a] || []).length;
            }).forEach(visit);
        }
        visit(start.id);
        nodes.forEach(function(n) {
            if (!seen[n.id]) { ordered.push(n); }
        });
        return ordered;
    }

    function renderNode(group, node, x, y, layout, graph, hiddenCount) {
        var kind = node.kind || "remote";
        var nodeR = (layout && layout.nodeR) ? layout.nodeR : NODE_R;
        var showLabels = !layout || layout.showLabels !== false;
        var expandable = hiddenCount > 0;
        group.setAttribute("class", "lldp-node " + kind + (expandable ? " expandable" : ""));
        group.setAttribute("data-node-id", node.id);
        group.style.cursor = "move";

        var title = document.createElementNS("http://www.w3.org/2000/svg", "title");
        title.textContent = node.label;
        group.appendChild(title);

        var circle;
        if (node.image) {
            var img = document.createElementNS("http://www.w3.org/2000/svg", "image");
            img.setAttribute("href", node.image);
            img.setAttribute("x", x - nodeR);
            img.setAttribute("y", y - nodeR);
            img.setAttribute("width", nodeR * 2);
            img.setAttribute("height", nodeR * 2);
            group.appendChild(img);
            circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", x);
            circle.setAttribute("cy", y);
            circle.setAttribute("r", nodeR);
            circle.setAttribute("fill", "transparent");
            group.appendChild(circle);
        } else {
            circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", x);
            circle.setAttribute("cy", y);
            circle.setAttribute("r", nodeR);
            group.appendChild(circle);
        }

        var text = null;
        if (showLabels) {
            text = document.createElementNS("http://www.w3.org/2000/svg", "text");
            var tx = x;
            var ty = y + nodeR + 12;
            if (layout && layout.cx !== undefined) {
                var dx = x - layout.cx;
                var dy = y - layout.cy;
                var len = Math.sqrt(dx * dx + dy * dy) || 1;
                tx = x + (dx / len) * (nodeR + 5);
                ty = y + (dy / len) * (nodeR + 5) + 9;
            }
            text.setAttribute("x", tx);
            text.setAttribute("y", ty);
            var label = node.label || "";
            if (label.length > 22) { label = label.substring(0, 20) + "..."; }
            text.textContent = label;
            group.appendChild(text);
        }

        var badge = null;
        if (expandable) {
            badge = document.createElementNS("http://www.w3.org/2000/svg", "text");
            badge.setAttribute("class", "lldp-node-badge");
            badge.setAttribute("x", x);
            badge.setAttribute("y", y - nodeR - 4);
            badge.textContent = "+" + hiddenCount;
            group.appendChild(badge);
        }

        group.addEventListener("mouseenter", function(e) {
            var kindLabel = kind === "local" ? labels.local : labels.remote;
            var layer = graph ? graph.layerMap[node.id] : 0;
            var html = "<strong>" + kindLabel + "</strong> (L" + (layer + 1) + ")<br>" + (node.label || "");
            if (expandable) {
                html += "<br><em>" + labels.clickExpand + " (" + hiddenCount + ")</em>";
            }
            html += "<br><em>" + labels.dragNode + "</em>";
            showTooltip(html, e);
        });
        group.addEventListener("mouseleave", hideTooltip);

        if (expandable) {
            group.addEventListener("dblclick", function(e) {
                e.preventDefault();
                e.stopPropagation();
                expandNodeNeighbors(node.id);
            });
        }

        return { group: group, circle: circle, text: text, badge: badge, nodeR: nodeR };
    }

    function renderTopology(data, keepReveal) {
        if (!canvasWrap) { return; }

        if (!keepReveal) {
            resetTopologyState();
        } else {
            topologyState.dom = { nodes: {}, edges: [] };
        }

        var svgOld = canvasWrap.querySelector("svg.lldp-svg");
        if (svgOld) { svgOld.remove(); }
        if (!keepReveal) {
            resetZoomState();
        } else {
            currentZoom = 1;
            panX = 0;
            panY = 0;
        }
        if (placeholder) { placeholder.style.display = "none"; }

        var graph = prepareGraph(data);
        if (graph.nodes.length === 0) {
            if (placeholder) {
                placeholder.textContent = labels.noData;
                placeholder.style.display = "flex";
            }
            if (layerHint) { layerHint.style.display = "none"; }
            return;
        }

        var components = findComponents(graph);
        var layout = layoutComponentsHorizontally(components, graph);
        Object.keys(topologyState.nodePositions).forEach(function(id) {
            if (layout.positions[id]) {
                layout.positions[id] = topologyState.nodePositions[id];
            }
        });

        if (layerHint) {
            layerHint.textContent = labels.layersShown;
            layerHint.style.display = "block";
        }

        var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("class", "lldp-svg");
        svg.setAttribute("width", layout.width);
        svg.setAttribute("height", layout.height);
        svg.setAttribute("viewBox", "0 0 " + layout.width + " " + layout.height);

        var rootG = document.createElementNS("http://www.w3.org/2000/svg", "g");
        rootG.setAttribute("id", "lldp-zoom-root");

        var compOffsetX = 20;
        layout.compLayouts.forEach(function(compInfo, idx) {
            var compLayout = compInfo.layout;
            var compG = document.createElementNS("http://www.w3.org/2000/svg", "g");
            compG.setAttribute("class", "lldp-component");

            if (components.length > 1) {
                var compTitle = document.createElementNS("http://www.w3.org/2000/svg", "text");
                compTitle.setAttribute("class", "lldp-component-title");
                compTitle.setAttribute("x", compOffsetX + compLayout.cx);
                compTitle.setAttribute("y", 14);
                compTitle.setAttribute("text-anchor", "middle");
                compTitle.textContent = labels.topologyGroup + " " + (idx + 1);
                compG.appendChild(compTitle);
            }

            var edgesG = document.createElementNS("http://www.w3.org/2000/svg", "g");
            compInfo.visibleEdges.forEach(function(edge) {
                var sp = getNodePosition(edge.source);
                var tp = getNodePosition(edge.target);
                if (!sp || !tp) { return; }

                var hit = document.createElementNS("http://www.w3.org/2000/svg", "line");
                hit.setAttribute("class", "lldp-edge-hit");
                hit.setAttribute("x1", sp.x);
                hit.setAttribute("y1", sp.y);
                hit.setAttribute("x2", tp.x);
                hit.setAttribute("y2", tp.y);

                var line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                line.setAttribute("class", "lldp-edge" + (edge.inferred ? " inferred" : ""));
                line.setAttribute("x1", sp.x);
                line.setAttribute("y1", sp.y);
                line.setAttribute("x2", tp.x);
                line.setAttribute("y2", tp.y);

                var tipHtml;
                if (edge.inferred) {
                    tipHtml = "<strong>" + labels.inferredLink + "</strong><br>"
                        + (edge.localDevice || "") + " ↔ " + (edge.remoteDevice || "");
                } else {
                    tipHtml = "<strong>" + labels.localDevice + "</strong> " + (edge.localDevice || "") + "<br>"
                        + "<strong>" + labels.localPort + "</strong> " + (edge.localPort || "") + "<br>"
                        + "<strong>" + labels.remoteDevice + "</strong> " + (edge.remoteDevice || "") + "<br>"
                        + "<strong>" + labels.remotePort + "</strong> " + (edge.remotePort || "") + "<br>"
                        + "<strong>" + labels.lastUpdate + "</strong> " + formatTime(edge.lastclock);
                }

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

                edgesG.appendChild(hit);
                edgesG.appendChild(line);
                topologyState.dom.edges.push({
                    source: edge.source,
                    target: edge.target,
                    line: line,
                    hit: hit
                });
            });
            compG.appendChild(edgesG);

            var nodesG = document.createElementNS("http://www.w3.org/2000/svg", "g");
            compInfo.visibleNodes.forEach(function(node) {
                var pos = getNodePosition(node.id);
                if (!pos) { return; }
                var hiddenCount = countHiddenNeighbors(node.id, graph);
                var g = document.createElementNS("http://www.w3.org/2000/svg", "g");
                var nodeDom = renderNode(g, node, pos.x, pos.y, compLayout, graph, hiddenCount);
                topologyState.dom.nodes[node.id] = nodeDom;
                nodesG.appendChild(g);
            });
            compG.appendChild(nodesG);
            rootG.appendChild(compG);

            compOffsetX += compLayout.width + COMPONENT_GAP;
        });

        svg.appendChild(rootG);
        canvasWrap.appendChild(svg);

        zoomSvg = svg;
        zoomRoot = rootG;
        zoomLayout = layout;
        renderTransform();
        setPinchable(true);
        setZoomControlsEnabled(true);
    }

    function runQuery() {
        if (!queryBtn) { return; }

        var groupid = document.getElementById("lldp-group-select");
        var hostid = document.getElementById("lldp-host-select");
        var params = new URLSearchParams();
        params.append("groupid", groupid ? groupid.value : "0");
        params.append("hostid", hostid ? hostid.value : "0");

        var originalText = queryBtn.textContent;
        queryBtn.disabled = true;
        queryBtn.textContent = labels.querying;
        if (statsEl) { statsEl.textContent = ""; }

        fetch("zabbix.php?action=lldp.data", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: params.toString()
        })
            .then(function(resp) { return resp.json(); })
            .then(function(result) {
                queryBtn.disabled = false;
                queryBtn.textContent = originalText;

                if (result && result.ok && result.data) {
                    fullTopologyData = result.data;
                    renderTopology(fullTopologyData);
                    if (statsEl) {
                        var txt = formatStats(result.data.stats);
                        if (result.data.stats && result.data.stats.skipped) {
                            txt += " (" + labels.skipped + ": " + result.data.stats.skipped + ")";
                        }
                        statsEl.textContent = txt;
                    }
                } else {
                    if (placeholder) {
                        placeholder.textContent = (result && result.message) ? result.message : labels.noData;
                        placeholder.style.display = "flex";
                    }
                    var svgOld = canvasWrap.querySelector("svg.lldp-svg");
                    if (svgOld) { svgOld.remove(); }
                    resetZoomState();
                    resetTopologyState();
                    fullTopologyData = null;
                    if (layerHint) { layerHint.style.display = "none"; }
                    alert((result && result.message) ? result.message : labels.queryFailed);
                }
            })
            .catch(function(err) {
                queryBtn.disabled = false;
                queryBtn.textContent = originalText;
                alert(labels.queryFailed + ": " + String(err));
            });
    }

    if (queryBtn) {
        queryBtn.addEventListener("click", runQuery);
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener("click", function() {
            applyZoom(currentZoom * ZOOM_STEP);
        });
    }

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener("click", function() {
            applyZoom(currentZoom / ZOOM_STEP);
        });
    }

    bindPinchZoom();

    window.addEventListener("resize", function() {
        if (fullTopologyData) {
            renderTopology(fullTopologyData, true);
        }
    });
})();