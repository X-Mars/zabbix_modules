    function buildTopologyGraph(nodes, edges, preferredRoot) {
        var graph = new Map(nodes.map(function(node) {
            return [node.id, { node: node, neighbors: new Set(), children: [] }];
        }));
        edges.forEach(function(edge) {
            if (edge.source === edge.target || !graph.has(edge.source) || !graph.has(edge.target)) { return; }
            graph.get(edge.source).neighbors.add(edge.target);
            graph.get(edge.target).neighbors.add(edge.source);
        });

        var ordered = Array.from(graph.keys()).sort(function(a, b) {
            var left = graph.get(a);
            var right = graph.get(b);
            return (b === preferredRoot) - (a === preferredRoot)
                || (right.node.kind === "local") - (left.node.kind === "local")
                || right.neighbors.size - left.neighbors.size
                || (a < b ? -1 : 1);
        });
        var rank = new Map(ordered.map(function(id, index) { return [id, index]; }));
        var components = [];
        ordered.forEach(function(root) {
            if (graph.get(root).depth !== undefined) { return; }
            graph.get(root).depth = 1;
            var queue = [root];
            for (var i = 0; i < queue.length; i++) {
                var id = queue[i];
                var entry = graph.get(id);
                Array.from(entry.neighbors).sort(function(a, b) { return rank.get(a) - rank.get(b); })
                    .forEach(function(next) {
                        var neighbor = graph.get(next);
                        if (neighbor.depth !== undefined) { return; }
                        neighbor.depth = entry.depth + 1;
                        neighbor.parent = id;
                        neighbor.branch = entry.branch || next;
                        entry.children.push(next);
                        queue.push(next);
                    });
            }
            components.push(queue);
        });
        return { graph: graph, components: components };
    }

    function getVisibleTopology(topology, edges, expanded, matches) {
        var graph = topology.graph;
        var forced = new Set();
        if (matches) {
            edges.filter(matches).forEach(function(edge) {
                [edge.source, edge.target].forEach(function(id) {
                    while (id !== undefined && graph.has(id) && !forced.has(id)) {
                        forced.add(id);
                        id = graph.get(id).parent;
                    }
                });
            });
        }

        var visible = new Set();
        var badges = new Map();
        topology.components.forEach(function(component) {
            component.forEach(function(id) {
                var entry = graph.get(id);
                if (entry.depth <= 3 || forced.has(id)
                    || (visible.has(entry.parent) && expanded.has(entry.parent))) {
                    visible.add(id);
                }
            });
            var hiddenSeverity = new Map();
            for (var i = component.length - 1; i >= 0; i--) {
                var id = component[i];
                var entry = graph.get(id);
                var hidden = entry.children.filter(function(child) { return !visible.has(child); });
                var severity = hidden.reduce(function(max, child) {
                    return Math.max(max, hiddenSeverity.get(child));
                }, -1);
                hiddenSeverity.set(id, Math.max(severity, Number(entry.node.severity ?? -1)));
                if (visible.has(id) && (hidden.length || (expanded.has(id) && entry.children.length))) {
                    badges.set(id, { count: hidden.length, severity: severity, open: expanded.has(id) });
                }
            }
        });
        return { visible: visible, badges: badges };
    }

    function layoutTopology(topology, visible) {
        var positions = Object.create(null);
        var regions = [];
        var width = 640;
        var y = 18;
        topology.components.forEach(function(component) {
            var root = component[0];
            var branches = new Map();
            component.slice(1).forEach(function(id) {
                if (!visible.has(id)) { return; }
                var entry = topology.graph.get(id);
                if (!branches.has(entry.branch)) { branches.set(entry.branch, new Map()); }
                var rows = branches.get(entry.branch);
                if (!rows.has(entry.depth)) { rows.set(entry.depth, []); }
                rows.get(entry.depth).push(id);
            });
            var x = 28;
            var bottom = y + 104;
            var firstY = y + 142;
            branches.forEach(function(rows, branch) {
                var columns = Math.max.apply(null, Array.from(rows.values(), function(row) { return row.length; }));
                var branchWidth = Math.max(200, columns * 156 + 24);
                var branchHeight = 42 + rows.size * 108;
                regions.push({ x: x, y: firstY - 36, width: branchWidth, height: branchHeight,
                    label: topology.graph.get(branch).node.label });
                rows.forEach(function(row, depth) {
                    row.forEach(function(id, index) {
                        positions[id] = {
                            x: x + branchWidth / 2 + (index - (row.length - 1) / 2) * 156,
                            y: firstY + (depth - 2) * 108
                        };
                    });
                });
                bottom = Math.max(bottom, firstY - 36 + branchHeight);
                x += branchWidth + 18;
            });
            var componentWidth = Math.max(320, x + 10);
            positions[root] = { x: componentWidth / 2, y: y + 45 };
            regions.unshift({ x: 10, y: y, width: componentWidth - 20, height: bottom - y + 16 });
            width = Math.max(width, componentWidth);
            y = bottom + 44;
        });
        var height = Math.max(420, y);
        return { positions: positions, regions: regions, width: width, height: height, cx: width / 2, cy: height / 2 };
    }
