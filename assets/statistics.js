$(document).on("rex:ready", function (event, container) {
    var statistics_datefilter_start = document.getElementById("statistics_datefilter_start");
    var statistics_df_lsd = document.getElementById("statistics_df_lsd");
    var statistics_df_ltd = document.getElementById("statistics_df_ltd");
    var statistics_df_ty = document.getElementById("statistics_df_ty");
    var statistics_df_wt = document.getElementById("statistics_df_wt");
    var statistics_df_form = document.getElementById("statistics_df_form");

    // if input field exists add event listeners
    if (statistics_datefilter_start != null && statistics_df_form && statistics_df_form.dataset.statisticsDatefilterBound !== 'true') {
        statistics_df_form.dataset.statisticsDatefilterBound = 'true';

        statistics_df_lsd.addEventListener("click", function () {
            var last_seven_days = get_past_date(7);
            statistics_datefilter_start.value = last_seven_days;
            statistics_df_form.submit();
        });
        statistics_df_ltd.addEventListener("click", function () {
            var last_thirty_days = get_past_date(30);
            statistics_datefilter_start.value = last_thirty_days;
            statistics_df_form.submit();
        });
        statistics_df_ty.addEventListener("click", function () {
            var last_year = get_past_date(365);
            statistics_datefilter_start.value = last_year;
            statistics_df_form.submit();
        });
        statistics_df_wt.addEventListener("click", function () {
            var whole_time = statistics_df_wt.getAttribute("data-start");
            statistics_datefilter_start.value = whole_time;
            statistics_df_form.submit();
        });
    }

    function get_past_date(minusDays) {
        var date = new Date();
        date.setDate(date.getDate() - minusDays);
        var day = ("0" + date.getDate()).slice(-2);
        var month = ("0" + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();
        var str = year + "-" + month + "-" + day;

        return str;
    }
});

(function () {
    var statisticsCharts = {};
    var statisticsPageConfigCache = null;
    var statisticsDashboardInitialized = false;
    var statisticsResizeScheduled = false;
    var statisticsTabsBound = false;
    var statisticsChartInitQueue = [];
    var statisticsChartQueueData = {};
    var statisticsChartQueueRunning = false;

    function isSafariBrowser() {
        var ua = (window.navigator && window.navigator.userAgent) || '';
        return /Safari\//.test(ua) && !/Chrome\//.test(ua) && !/Chromium\//.test(ua) && !/CriOS\//.test(ua) && !/Edg\//.test(ua);
    }

    function normalizeChartOption(option) {
        if (!option || typeof option !== 'object') {
            return option;
        }

        if (isSafariBrowser()) {
            option.animation = false;
            option.animationDuration = 0;
            option.animationDurationUpdate = 0;
        }

        return option;
    }

    function getPageConfig() {
        if (statisticsPageConfigCache !== null) {
            return statisticsPageConfigCache;
        }

        var configElement = document.getElementById('statistics-page-config');
        if (!configElement) {
            statisticsPageConfigCache = null;
            return statisticsPageConfigCache;
        }

        try {
            statisticsPageConfigCache = JSON.parse(configElement.textContent || '{}');
        } catch (error) {
            statisticsPageConfigCache = null;
        }

        return statisticsPageConfigCache;
    }

    function getTheme() {
        if (typeof rex !== "undefined" && (rex.theme == "dark" || (window.matchMedia('(prefers-color-scheme: dark)').matches && rex.theme == "auto"))) {
            return "dark";
        }

        return "shine";
    }

    function getLanguageOptions() {
        var pageConfig = getPageConfig();
        if (pageConfig && pageConfig.tableLanguage) {
            return { language: pageConfig.tableLanguage };
        }

        return {};
    }

    function buildToolbox(showToolbox, includeMagicType) {
        var feature = {
            dataZoom: {
                yAxisIndex: 'none'
            },
            dataView: {
                readOnly: false
            },
            restore: {},
            saveAsImage: {}
        };

        if (includeMagicType) {
            feature.magicType = {
                type: ['line', 'bar', 'stack']
            };
        }

        return {
            show: !!showToolbox,
            orient: 'vertical',
            top: '10%',
            feature: feature
        };
    }

    function rememberChart(elementId, chart) {
        if (!elementId || !chart) {
            return chart;
        }

        statisticsCharts[elementId] = chart;
        return chart;
    }

    function cleanupDisposedCharts() {
        Object.keys(statisticsCharts).forEach(function (id) {
            var chart = statisticsCharts[id];
            if (!chart || chart.isDisposed()) {
                delete statisticsCharts[id];
            }
        });
    }

    function createOrReplaceChart(elementId, option) {
        if (typeof echarts === 'undefined') {
            return null;
        }

        var element = document.getElementById(elementId);
        if (!element) {
            return null;
        }

        var normalizedOption = normalizeChartOption(option);
        var existing = echarts.getInstanceByDom(element);
        if (existing) {
            existing.setOption(normalizedOption, {
                notMerge: true,
                lazyUpdate: true,
                silent: true
            });
            return rememberChart(elementId, existing);
        }

        var chart = echarts.init(element, getTheme());
        chart.setOption(normalizedOption, {
            notMerge: true,
            lazyUpdate: true,
            silent: true
        });
        return rememberChart(elementId, chart);
    }

    function getChartQueueDelay() {
        return isSafariBrowser() ? 40 : 12;
    }

    function scheduleChartQueueTick(callback) {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(callback, { timeout: 120 });
            return;
        }

        window.setTimeout(callback, getChartQueueDelay());
    }

    function deferInitialCharts() {
        var delay = isSafariBrowser() ? 220 : 80;

        window.setTimeout(function () {
            initStatisticsPageCharts();
            initConfiguredCharts(document);
        }, delay);
    }

    function runChartQueue() {
        if (statisticsChartQueueRunning) {
            return;
        }

        statisticsChartQueueRunning = true;

        scheduleChartQueueTick(function () {
            var nextId = statisticsChartInitQueue.shift();
            if (!nextId) {
                statisticsChartQueueRunning = false;
                return;
            }

            var option = statisticsChartQueueData[nextId];
            delete statisticsChartQueueData[nextId];

            if (option && createOrReplaceChart(nextId, option)) {
                scheduleChartResize(nextId);
            }

            statisticsChartQueueRunning = false;
            if (statisticsChartInitQueue.length > 0) {
                runChartQueue();
            }
        });
    }

    function queueChartInit(elementId, option) {
        if (!elementId || !option) {
            return;
        }

        statisticsChartQueueData[elementId] = option;
        if (statisticsChartInitQueue.indexOf(elementId) === -1) {
            statisticsChartInitQueue.push(elementId);
        }

        runChartQueue();
    }

    function scheduleChartResize(elementId) {
        window.requestAnimationFrame(function () {
            window.statisticsResizeChartById(elementId);

            window.setTimeout(function () {
                window.statisticsResizeChartById(elementId);
            }, 50);
        });
    }

    function buildDailyChartOption(pageConfig) {
        var enhancedSeries = (pageConfig.mainChartData.series || []).map(function (entry) {
            var next = Object.assign({}, entry);
            var name = String(next.name || '');
            var isTotal = name.indexOf('Gesamt') !== -1;

            next.type = 'line';
            next.smooth = true;
            next.showSymbol = false;
            next.sampling = 'lttb';

            if (!isTotal && name.indexOf('Aufrufe ') === 0) {
                next.stack = 'Aufrufe';
                next.areaStyle = { opacity: 0.1 };
            }

            if (!isTotal && name.indexOf('Besucher ') === 0) {
                next.stack = 'Besucher';
                next.areaStyle = { opacity: 0.08 };
            }

            if (isTotal) {
                next.lineStyle = { width: 3 };
                next.z = 10;
            }

            return next;
        });

        return {
            title: {},
            tooltip: {
                trigger: 'axis'
            },
            dataZoom: [{
                id: 'dataZoomX',
                type: 'slider',
                xAxisIndex: [0],
                filterMode: 'filter'
            }],
            grid: {
                left: '5%',
                right: '5%'
            },
            toolbox: buildToolbox(pageConfig.showToolbox, true),
            legend: {
                data: pageConfig.mainChartData.legend,
                type: 'scroll',
                right: '5%',
                align: 'left'
            },
            xAxis: {
                data: pageConfig.mainChartData.xaxis,
                type: 'category',
                boundaryGap: false
            },
            yAxis: {},
            series: enhancedSeries
        };
    }

    function buildHeatmapOption(pageConfig) {
        return {
            title: {},
            tooltip: {
                show: true,
                formatter: function (p) {
                    var format = echarts.format.formatTime('dd.MM.yyyy', p.data[0]);
                    return format + '<br><b>' + p.data[1] + ' Aufrufe</b>';
                }
            },
            toolbox: {
                show: !!pageConfig.showToolbox,
                orient: 'vertical',
                top: '10%',
                feature: {
                    dataView: {
                        readOnly: false
                    },
                    restore: {},
                    saveAsImage: {}
                }
            },
            calendar: {
                top: '90',
                left: '5%',
                right: '5%',
                cellSize: ['auto', 15],
                range: pageConfig.heatmap.year,
                itemStyle: {
                    borderWidth: 0.5
                },
                yearLabel: {
                    show: false
                },
                monthLabel: {
                    nameMap: ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
                },
                dayLabel: {
                    nameMap: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']
                }
            },
            series: {
                data: pageConfig.heatmap.data,
                type: 'heatmap',
                coordinateSystem: 'calendar'
            },
            visualMap: {
                type: 'continuous',
                itemWidth: 20,
                itemHeight: 250,
                min: 0,
                max: pageConfig.heatmap.max,
                calculable: true,
                orient: 'horizontal',
                left: 'center',
                top: 'top'
            }
        };
    }

    function initStatisticsPageCharts() {
        var pageConfig = getPageConfig();
        if (!pageConfig || !pageConfig.mainChartData || !pageConfig.heatmap) {
            return;
        }

        queueChartInit('chart_visits_daily', buildDailyChartOption(pageConfig));
        queueChartInit('chart_visits_heatmap', buildHeatmapOption(pageConfig));
    }

    function initConfiguredCharts(root) {
        var container = root || document;
        var scripts = container.querySelectorAll('[data-statistics-chart-config]');

        scripts.forEach(function (script) {
            var targetId = script.getAttribute('data-target-id');
            if (!targetId) {
                return;
            }

            try {
                var option = JSON.parse(script.textContent || '{}');
                queueChartInit(targetId, option);
            } catch (error) {
            }
        });
    }

    function initPagesDomainFilter() {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
            return;
        }

        var statsTableAllPagesElement = document.querySelector('.dt_order_second');
        var statsDomainSelect = document.getElementById('stats_domain_select');

        if (!statsTableAllPagesElement || !statsDomainSelect) {
            return;
        }

        if (!$.fn.dataTable.isDataTable(statsTableAllPagesElement)) {
            return;
        }

        if (statsDomainSelect.dataset.statisticsBound === 'true') {
            return;
        }

        statsDomainSelect.dataset.statisticsBound = 'true';
        var statsTableAllPages = $(statsTableAllPagesElement).DataTable();

        statsDomainSelect.addEventListener('change', function () {
            statsTableAllPages.search(this.value).draw();
        });
    }

    function bootstrapStatisticsDashboard() {
        if (statisticsDashboardInitialized) {
            deferInitialCharts();
            return;
        }

        statisticsDashboardInitialized = true;
        initStatsTabHandling();
        window.statisticsInitTables(document);
        initPagesDomainFilter();
        initLazyBlocks();
        initLazyCollapses();
        initAnalysisCards();
        deferInitialCharts();
    }

    function initStatsTabHandling() {
        if (typeof $ === 'undefined' || statisticsTabsBound) {
            return;
        }

        statisticsTabsBound = true;

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            window.statisticsResizeChartById('chart_visits_daily');
            window.statisticsResizeChartById('chart_visits_monthly');
            window.statisticsResizeChartById('chart_visits_yearly');
            window.statisticsEnsureTabChartLoaded(e.target);
        });

        var activeTab = document.querySelector('.nav.nav-pills li.active [data-toggle="tab"]');
        if (activeTab) {
            window.statisticsEnsureTabChartLoaded(activeTab);
        }
    }

    function buildTableOptions(orderIndex, caseInsensitive, element) {
        var pageLength = 10;
        if (element && element.dataset && element.dataset.pageLength) {
            var parsedPageLength = parseInt(element.dataset.pageLength, 10);
            if (!isNaN(parsedPageLength) && parsedPageLength > 0) {
                pageLength = parsedPageLength;
            }
        }

        var options = {
            paging: true,
            pageLength: pageLength,
            lengthChange: true,
            lengthMenu: [5, 10, 50, 100],
            search: {
                caseInsensitive: caseInsensitive
            }
        };

        if (orderIndex !== null) {
            options.order = [[orderIndex, "desc"]];
        }

        return Object.assign(options, getLanguageOptions());
    }

    window.statisticsInitTables = function (container) {
        var root = container || document;

        if (typeof $ === "undefined" || !$.fn || !$.fn.DataTable) {
            return;
        }

        $(root).find('.dt_order_second').each(function () {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable(buildTableOptions(1, true, this));
            }
        });

        $(root).find('.dt_order_first').each(function () {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable(buildTableOptions(0, true, this));
            }
        });

        $(root).find('.dt_order_default').each(function () {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable(buildTableOptions(null, true, this));
            }
        });

        $(root).find('.dt_bots').each(function () {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable(buildTableOptions(1, true, this));
            }
        });
    };

    function initCharts(charts) {
        if (typeof echarts === "undefined" || !charts || !charts.length) {
            return;
        }

        charts.forEach(function (chartConfig) {
            queueChartInit(chartConfig.id, chartConfig.option);
        });

        cleanupDisposedCharts();
    }

    function fetchLazyPayload(blockId, dateStart, dateEnd) {
        var url = new URL(window.location.href);
        url.searchParams.set('rex-api-call', 'statistics_lazy_block');
        url.searchParams.set('block_id', blockId);
        url.searchParams.set('date_start', dateStart || '');
        url.searchParams.set('date_end', dateEnd || '');

        return fetch(url.toString(), { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            });
    }

    function renderLoading(container) {
        container.innerHTML = '<div class="panel panel-default"><div class="panel-body"><p><i class="fa fa-spinner fa-spin"></i> Statistikblock wird geladen...</p></div></div>';
    }

    function renderError(container) {
        container.innerHTML = '<div class="alert alert-danger">Der Statistikblock konnte nicht geladen werden. Bitte Seite neu laden.</div>';
    }

    function renderInlineLoading(container) {
        container.innerHTML = '<p><i class="fa fa-spinner fa-spin"></i> Tabelle wird geladen...</p>';
    }

    function renderInlineError(container) {
        container.innerHTML = '<div class="alert alert-danger">Die Tabelle konnte nicht geladen werden.</div>';
    }

    function loadLazyBlock(container) {
        if (!container || container.dataset.state === 'loading' || container.dataset.state === 'loaded') {
            return;
        }

        container.dataset.state = 'loading';
        renderLoading(container);

        fetchLazyPayload(container.dataset.blockId, container.dataset.dateStart, container.dataset.dateEnd)
            .then(function (payload) {
                container.innerHTML = payload.html || '';
                window.statisticsInitTables(container);
                initCharts(payload.charts || []);
                container.dataset.state = 'loaded';
            })
            .catch(function () {
                container.dataset.state = 'error';
                renderError(container);
            });
    }

    function initAnalysisCards() {
        var cards = document.querySelectorAll('[data-statistics-focus-lazy]');
        if (!cards.length) {
            return;
        }

        cards.forEach(function (card) {
            if (card.dataset.statisticsBound === 'true') {
                return;
            }

            card.dataset.statisticsBound = 'true';
            card.addEventListener('click', function () {
                var targetId = card.getAttribute('data-statistics-focus-lazy');
                if (!targetId) {
                    return;
                }

                var target = document.getElementById(targetId);
                if (!target) {
                    return;
                }

                loadLazyBlock(target);
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function initLazyBlocks() {
        var blocks = document.querySelectorAll('[data-statistics-lazy-block]');
        if (!blocks.length) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            blocks.forEach(loadLazyBlock);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    loadLazyBlock(entry.target);
                }
            });
        }, {
            rootMargin: '200px 0px'
        });

        blocks.forEach(function (block) {
            observer.observe(block);
        });
    }

    function loadLazyCollapse(container) {
        if (!container || container.dataset.state === 'loading' || container.dataset.state === 'loaded') {
            return;
        }

        container.dataset.state = 'loading';
        renderInlineLoading(container);

        fetchLazyPayload(container.dataset.blockId, container.dataset.dateStart, container.dataset.dateEnd)
            .then(function (payload) {
                container.innerHTML = payload.html || '';
                window.statisticsInitTables(container);
                container.dataset.state = 'loaded';
            })
            .catch(function () {
                container.dataset.state = 'error';
                renderInlineError(container);
            });
    }

    function initLazyCollapses() {
        if (typeof $ === 'undefined') {
            return;
        }

        $('[data-statistics-lazy-collapse]').each(function () {
            var lazyContainer = this;
            var collapse = $(lazyContainer).closest('.collapse');

            collapse.on('show.bs.collapse', function () {
                loadLazyCollapse(lazyContainer);
            });
        });
    }

    function loadLazyChart(element) {
        if (!element || element.dataset.state === 'loading' || element.dataset.state === 'loaded') {
            return;
        }

        element.dataset.state = 'loading';

        fetchLazyPayload(element.dataset.blockId, element.dataset.dateStart, element.dataset.dateEnd)
            .then(function (payload) {
                initCharts(payload.charts || []);
                element.dataset.state = 'loaded';
            })
            .catch(function () {
                element.dataset.state = 'error';
            });
    }

    window.statisticsEnsureTabChartLoaded = function (tabLink) {
        if (!tabLink) {
            return;
        }

        var targetSelector = tabLink.getAttribute('href');
        if (!targetSelector) {
            return;
        }

        var pane = document.querySelector(targetSelector);
        if (!pane) {
            return;
        }

        var lazyChart = pane.querySelector('[data-statistics-lazy-chart]');
        if (lazyChart) {
            loadLazyChart(lazyChart);
        }
    };

    window.statisticsResizeChartById = function (id) {
        if (typeof echarts === 'undefined') {
            return;
        }

        var element = document.getElementById(id);
        if (!element) {
            return;
        }

        var chart = echarts.getInstanceByDom(element);
        if (chart) {
            chart.resize();
        }
    };

    window.addEventListener('resize', function () {
        if (statisticsResizeScheduled) {
            return;
        }

        statisticsResizeScheduled = true;
        window.requestAnimationFrame(function () {
            statisticsResizeScheduled = false;
            cleanupDisposedCharts();

            Object.keys(statisticsCharts).forEach(function (id) {
                var chart = statisticsCharts[id];
                if (chart && !chart.isDisposed()) {
                    chart.resize();
                }
            });
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapStatisticsDashboard);
    } else {
        bootstrapStatisticsDashboard();
    }

    $(document).on('rex:ready', function () {
        bootstrapStatisticsDashboard();
    });
})();
