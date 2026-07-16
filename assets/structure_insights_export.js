(function ($) {
    'use strict';

    window.__statisticsExportJsLoaded = true;

    function isStructureInsightsPage() {
        var search = window.location.search || '';
        return search.indexOf('page=statistics%2Fstructure_insights') !== -1
            || search.indexOf('page=statistics/structure_insights') !== -1;
    }

    function triggerDownload(url) {
        url = String(url).replace(/&amp;/g, '&');

        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);

        window.setTimeout(function () {
            if (iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }
        }, 10000);
    }

    function collectTableData() {
        var headers = [];
        var rows = [];

        $('.table thead th').each(function () {
            headers.push($(this).text().trim());
        });

        $('.table tbody tr').each(function () {
            var $tr = $(this);
            var depth = parseInt($tr.attr('data-depth') || '0', 10);
            var line = [];

            $tr.find('td').each(function () {
                line.push($(this).text().replace(/\u00a0/g, ' ').trim());
            });

            rows.push({
                depth: isNaN(depth) ? 0 : depth,
                values: line,
            });
        });

        return {
            headers: headers,
            rows: rows,
        };
    }

    function autosizeColumns(worksheet) {
        worksheet.columns.forEach(function (column) {
            var maxLength = 10;
            column.eachCell({ includeEmpty: true }, function (cell) {
                var value = cell.value == null ? '' : String(cell.value);
                maxLength = Math.max(maxLength, value.length + 2);
            });
            column.width = Math.min(80, maxLength);
        });
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

    function createXlsxExport() {
        window.__statisticsXlsxClicked = true;
        window.__statisticsXlsxError = null;

        if (typeof window.ExcelJS === 'undefined') {
            window.__statisticsXlsxError = 'ExcelJS missing';
            window.alert('XLSX-Export ist nicht verfügbar (ExcelJS fehlt).');
            return;
        }

        var data = collectTableData();
        if (!data.headers.length) {
            return;
        }

        var workbook = new window.ExcelJS.Workbook();
        workbook.creator = 'REDAXO statistics';
        workbook.created = new Date();

        var normalize = function (value) {
            return String(value || '').trim().toLowerCase();
        };

        var findHeaderIndex = function (headers, candidates) {
            var normalized = headers.map(normalize);
            for (var i = 0; i < candidates.length; i++) {
                var idx = normalized.indexOf(candidates[i]);
                if (idx !== -1) {
                    return idx;
                }
            }
            return -1;
        };

        var buildTreeTitle = function (depth, typeText, rawTitle) {
            var cleanTitle = String(rawTitle || '').replace(/^\[[KA]\]\s*/i, '').trim();
            var marker = /kategorie|category/i.test(String(typeText || '')) ? '[K]' : '[A]';
            var branch = '';

            for (var i = 0; i < depth; i++) {
                branch += (i === depth - 1) ? '|- ' : '|  ';
            }

            return branch + marker + ' ' + cleanTitle;
        };

        var exportHeaders = data.headers.slice();
        var hasDepthHeader = exportHeaders.some(function (header) {
            var v = normalize(header);
            return v === 'ebene' || v === 'level';
        });
        if (!hasDepthHeader) {
            exportHeaders.unshift('Ebene');
        }

        var titleIndex = findHeaderIndex(exportHeaders, ['titel', 'title']);
        var typeIndex = findHeaderIndex(exportHeaders, ['typ', 'type']);

        var worksheet = workbook.addWorksheet('Struktur', {
            properties: { outlineLevelRow: 7 },
            views: [{ state: 'frozen', ySplit: 1 }],
        });

        var headerRow = worksheet.addRow(exportHeaders);
        headerRow.font = { bold: true };

        data.rows.forEach(function (entry) {
            var values = entry.values.slice();
            if (!hasDepthHeader) {
                values.unshift(String(entry.depth));
            }

            if (titleIndex >= 0) {
                var typeText = typeIndex >= 0 ? values[typeIndex] : '';
                values[titleIndex] = buildTreeTitle(entry.depth, typeText, values[titleIndex]);
            }

            var row = worksheet.addRow(values);
            if (entry.depth > 0) {
                row.outlineLevel = Math.min(7, entry.depth);
            }

            if (titleIndex >= 0) {
                var titleCell = row.getCell(titleIndex + 1);
                titleCell.alignment = {
                    horizontal: 'left',
                    vertical: 'middle',
                    indent: Math.min(15, entry.depth * 2),
                    wrapText: false,
                };
            }
        });

        worksheet.autoFilter = {
            from: { row: 1, column: 1 },
            to: { row: 1, column: exportHeaders.length },
        };

        autosizeColumns(worksheet);

        workbook.xlsx.writeBuffer().then(function (buffer) {
            var now = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var stamp = now.getFullYear()
                + pad(now.getMonth() + 1)
                + pad(now.getDate())
                + '-'
                + pad(now.getHours())
                + pad(now.getMinutes())
                + pad(now.getSeconds());
            var filename = 'statistics-structure-insights-' + stamp + '.xlsx';
            downloadBlob(filename, new Blob([buffer], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            }));
        }).catch(function () {
            window.__statisticsXlsxError = 'writeBuffer failed';
            window.alert('XLSX-Export konnte nicht erstellt werden.');
        });
    }

    function bindExportHandlers() {
        if (!isStructureInsightsPage()) {
            return;
        }

        $(document)
            .off('click.statisticsStructureExport')
            .on('click.statisticsStructureExport', 'a.js-statistics-structure-export', function (event) {
                var format = $(this).attr('data-export-format') || '';
                if (format === 'xlsx') {
                    event.preventDefault();
                    event.stopPropagation();
                    createXlsxExport();
                    return;
                }

                var url = $(this).attr('href');
                if (!url) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                triggerDownload(url);
            });
    }

    bindExportHandlers();
    $(bindExportHandlers);
    $(document).on('rex:ready', bindExportHandlers);
})(jQuery);
