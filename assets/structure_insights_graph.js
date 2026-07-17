(function ($) {
    'use strict';

    var graphState = {
        scale: 1,
        minScale: 0.25,
        maxScale: 3,
    };

    function isStructureInsightsPage() {
        var search = window.location.search || '';
        return search.indexOf('page=statistics%2Fstructure_insights') !== -1
            || search.indexOf('page=statistics/structure_insights') !== -1;
    }

    function parseRows() {
        var rows = [];
        $('.table tbody tr').each(function () {
            var $tr = $(this);
            var $td = $tr.find('td');
            if ($td.length < 7) {
                return;
            }

            var depth = parseInt($tr.attr('data-depth') || '0', 10);
            rows.push({
                depth: isNaN(depth) ? 0 : depth,
                type: ($td.eq(0).text() || '').trim(),
                id: ($td.eq(1).text() || '').trim(),
                title: ($td.eq(2).text() || '').replace(/\u00a0/g, ' ').trim(),
                status: ($td.eq(3).text() || '').trim().toLowerCase(),
            });
        });

        return rows;
    }

    function buildTree(flatRows, rootLabel) {
        var root = {
            depth: -1,
            type: 'root',
            id: '0',
            title: rootLabel,
            status: 'online',
            children: [],
        };
        var stack = [root];

        flatRows.forEach(function (row) {
            var node = {
                depth: row.depth,
                type: row.type,
                id: row.id,
                title: row.title,
                status: row.status,
                children: [],
            };

            var targetDepth = Math.max(0, row.depth);
            while (stack.length > targetDepth + 1) {
                stack.pop();
            }

            if (!stack.length) {
                stack = [root];
            }

            stack[stack.length - 1].children.push(node);
            stack.push(node);
        });

        return root;
    }

    function layoutTree(root) {
        var leafCounter = 0;
        var maxDepth = 0;

        function walk(node, depth) {
            node.level = depth;
            maxDepth = Math.max(maxDepth, depth);

            if (!node.children.length) {
                node.x = 120 + leafCounter * 260;
                leafCounter += 1;
            } else {
                node.children.forEach(function (child) {
                    walk(child, depth + 1);
                });
                node.x = (node.children[0].x + node.children[node.children.length - 1].x) / 2;
            }

            node.y = 40 + depth * 170;
        }

        walk(root, 0);

        return {
            leafCount: Math.max(leafCounter, 1),
            maxDepth: maxDepth,
        };
    }

    function escapeXml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildSvgMarkup(root) {
        var metrics = layoutTree(root);
        var width = Math.max(900, metrics.leafCount * 260 + 160);
        var height = Math.max(320, (metrics.maxDepth + 1) * 170 + 120);

        var parts = [];
        parts.push('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" width="100%" height="' + height + '" role="img">');
        parts.push('<defs>');
        parts.push('<pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse">');
        parts.push('<path d="M 48 0 L 0 0 0 48" fill="none" stroke="#d9e2f0" stroke-opacity="0.7" stroke-width="1"/>');
        parts.push('</pattern>');
        parts.push('<linearGradient id="nodeCategory" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#f7b7de"/><stop offset="100%" stop-color="#ffa28e"/></linearGradient>');
        parts.push('<linearGradient id="nodeArticle" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c4d7ff"/><stop offset="100%" stop-color="#e3edff"/></linearGradient>');
        parts.push('</defs>');
        parts.push('<rect x="0" y="0" width="' + width + '" height="' + height + '" fill="#ffffff"/>');
        parts.push('<rect x="0" y="0" width="' + width + '" height="' + height + '" fill="url(#grid)"/>');

        function nodeType(node) {
            if (node.type === 'root') {
                return 'root';
            }
            return /kategorie|category/i.test(node.type) ? 'category' : 'article';
        }

        function edgePath(parent, child) {
            var x1 = parent.x;
            var y1 = parent.y + 42;
            var x2 = child.x;
            var y2 = child.y - 42;
            var midY = (y1 + y2) / 2;
            return 'M ' + x1 + ' ' + y1 + ' C ' + x1 + ' ' + midY + ', ' + x2 + ' ' + midY + ', ' + x2 + ' ' + y2;
        }

        function drawEdges(node) {
            node.children.forEach(function (child) {
                parts.push('<path d="' + edgePath(node, child) + '" stroke="#223961" stroke-width="3" fill="none"/>');
                parts.push('<circle cx="' + child.x + '" cy="' + (child.y - 42) + '" r="4" fill="#f4d2e7" stroke="#223961" stroke-width="2"/>');
                drawEdges(child);
            });
        }

        drawEdges(root);

        function drawNode(node) {
            var kind = nodeType(node);
            var w = 230;
            var h = 84;
            var x = node.x - w / 2;
            var y = node.y - h / 2;
            var fill = 'url(#nodeArticle)';
            if (kind === 'root' || kind === 'category') {
                fill = 'url(#nodeCategory)';
            }

            var statusColor = '#8ba4d6';
            if (node.status === 'online') {
                statusColor = '#69d28a';
            } else if (node.status === 'offline') {
                statusColor = '#d27575';
            }

            var label = node.title;
            if (kind !== 'root' && node.id !== '') {
                label = '[' + node.id + '] ' + label;
            }
            if (label.length > 34) {
                label = label.slice(0, 31) + '...';
            }

            parts.push('<g>');
            parts.push('<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="12" ry="12" fill="' + fill + '" stroke="#223961" stroke-width="3"/>');
            parts.push('<circle cx="' + (x + 14) + '" cy="' + (y + 14) + '" r="6" fill="' + statusColor + '" stroke="#223961" stroke-width="2"/>');
            parts.push('<text x="' + (x + 28) + '" y="' + (y + 24) + '" fill="#203556" font-family="Aptos, Segoe UI, sans-serif" font-size="12" font-weight="700">' + escapeXml(kind === 'root' ? '' : node.type) + '</text>');
            parts.push('<text x="' + (x + 14) + '" y="' + (y + 50) + '" fill="#203556" font-family="Aptos, Segoe UI, sans-serif" font-size="14" font-weight="700">' + escapeXml(label) + '</text>');
            parts.push('</g>');

            node.children.forEach(drawNode);
        }

        drawNode(root);
        parts.push('</svg>');

        return parts.join('');
    }

    function getCurrentSvg() {
        return $('#statistics-structure-graph svg').get(0) || null;
    }

    function serializeSvg(svgElement) {
        if (!svgElement) {
            return '';
        }

        var clone = svgElement.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        if (!clone.getAttribute('xmlns:xlink')) {
            clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        }

        return '<?xml version="1.0" encoding="UTF-8"?>\n' + clone.outerHTML;
    }

    function timestamp() {
        var now = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '-' + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds());
    }

    function downloadBlob(filename, blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();

        window.setTimeout(function () {
            URL.revokeObjectURL(url);
            if (a.parentNode) {
                a.parentNode.removeChild(a);
            }
        }, 1000);
    }

    function exportSvg() {
        var svg = getCurrentSvg();
        if (!svg) {
            return;
        }

        var content = serializeSvg(svg);
        downloadBlob('statistics-sitemap-' + timestamp() + '.svg', new Blob([content], {
            type: 'image/svg+xml;charset=utf-8',
        }));
    }

    function applyScale() {
        var $svg = $('#statistics-graph-modal-canvas svg');
        if (!$svg.length) {
            return;
        }

        $svg.css('transform', 'scale(' + graphState.scale + ')');
    }

    function setScale(next) {
        graphState.scale = Math.max(graphState.minScale, Math.min(graphState.maxScale, next));
        applyScale();
    }

    function openModalWithGraph() {
        var svg = getCurrentSvg();
        if (!svg) {
            return;
        }

        var $canvas = $('#statistics-graph-modal-canvas');
        $canvas.empty().append($(svg.cloneNode(true)));
        graphState.scale = 1;
        applyScale();

        $('#statistics-graph-modal').modal('show');
    }

    function bindGraphActions() {
        $(document)
            .off('click.statisticsGraphOpen')
            .on('click.statisticsGraphOpen', '#statistics-graph-open-modal', function () {
                openModalWithGraph();
            })
            .off('click.statisticsGraphExportSvg')
            .on('click.statisticsGraphExportSvg', '#statistics-graph-export-svg, [data-graph-export="svg"]', function () {
                exportSvg();
            })
            .off('click.statisticsGraphZoom')
            .on('click.statisticsGraphZoom', '[data-graph-zoom]', function () {
                var action = $(this).attr('data-graph-zoom');
                if (action === 'in') {
                    setScale(graphState.scale + 0.2);
                } else if (action === 'out') {
                    setScale(graphState.scale - 0.2);
                } else {
                    setScale(1);
                }
            });

        $('#statistics-graph-modal-canvas')
            .off('wheel.statisticsGraphZoom')
            .on('wheel.statisticsGraphZoom', function (event) {
                if (!event.ctrlKey) {
                    return;
                }

                event.preventDefault();
                var delta = event.originalEvent.deltaY;
                if (delta < 0) {
                    setScale(graphState.scale + 0.1);
                } else {
                    setScale(graphState.scale - 0.1);
                }
            });
    }

    function renderGraph() {
        if (!isStructureInsightsPage()) {
            return;
        }

        var $container = $('#statistics-structure-graph');
        if (!$container.length) {
            return;
        }

        var rows = parseRows();
        if (!rows.length) {
            var emptyText = $container.attr('data-empty-text') || 'No data available.';
            $container.html('<div class="alert alert-info" style="margin-bottom:0;">' + escapeXml(emptyText) + '</div>');
            return;
        }

        var rootLabel = $container.attr('data-root-label') || 'Website';
        var tree = buildTree(rows, rootLabel);
        var svgMarkup = buildSvgMarkup(tree);

        $container.html('<div class="statistics-graph-frame">' + svgMarkup + '</div>');
    }

    function init() {
        if (!isStructureInsightsPage()) {
            return;
        }

        bindGraphActions();
        renderGraph();
    }

    $(init);
    $(document).on('rex:ready', init);
})(jQuery);
