<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_config;
use rex_fragment;
use rex_sql;
use rex_view;

class StatsLazyBlockRenderer
{
    private DateFilter $filter_date_helper;
    private rex_addon $addon;

    public function __construct(DateFilter $filter_date_helper)
    {
        $this->filter_date_helper = $filter_date_helper;
        $this->addon = rex_addon::get('statistics');
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    public function render(string $blockId): array
    {
        if ('device' === $blockId) {
            return $this->renderDeviceBlock();
        }

        if ('extended' === $blockId) {
            return $this->renderExtendedBlock();
        }

        if ('bots' === $blockId) {
            return $this->renderBotsBlock();
        }

        if ('main-daily-tables' === $blockId) {
            return $this->renderMainListBlock('daily');
        }

        if ('main-monthly-tables' === $blockId) {
            return $this->renderMainListBlock('monthly');
        }

        if ('main-yearly-tables' === $blockId) {
            return $this->renderMainListBlock('yearly');
        }

        if ('main-monthly-chart' === $blockId) {
            return $this->renderMainChartBlock('monthly');
        }

        if ('main-yearly-chart' === $blockId) {
            return $this->renderMainChartBlock('yearly');
        }

        throw new \InvalidArgumentException('Unknown block id: ' . $blockId);
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderDeviceBlock(): array
    {
        $browser = new Browser();
        $browsertype = new Browsertype();
        $os = new OS();
        $brand = new Brand();
        $model = new Model();
        $weekday = new Weekday();
        $hour = new Hour();

        $html = '';
        $charts = [];

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_browser'), 'chart_browser', $browser->getList());
        $charts[] = ['id' => 'chart_browser', 'option' => $this->buildTopCategoriesBarOption($browser->getData(), '{b}: <b>{c}</b>')];

        $deviceAndBrandTable = '<h5><b>' . htmlspecialchars($this->addon->i18n('statistics_devicetype'), ENT_QUOTES) . '</b></h5>'
            . $browsertype->getList()
            . '<hr>'
            . '<h5><b>' . htmlspecialchars($this->addon->i18n('statistics_brand'), ENT_QUOTES) . '</b></h5>'
            . $brand->getList()
            . '<hr>'
            . '<h5><b>' . htmlspecialchars($this->addon->i18n('statistics_os'), ENT_QUOTES) . '</b></h5>'
            . $os->getList();
        $deviceCharts = ''
            . '<h5><b>' . htmlspecialchars($this->addon->i18n('statistics_devicetype'), ENT_QUOTES) . '</b></h5>'
            . '<div id="chart_device_type_compact" style="width:100%;height:180px"></div>'
            . '<h5 style="margin-top:18px"><b>' . htmlspecialchars($this->addon->i18n('statistics_brand'), ENT_QUOTES) . '</b></h5>'
            . '<div id="chart_device_brand_compact" style="width:100%;height:220px"></div>'
            . '<h5 style="margin-top:18px"><b>' . htmlspecialchars($this->addon->i18n('statistics_os'), ENT_QUOTES) . '</b></h5>'
            . '<div id="chart_device_os_compact" style="width:100%;height:220px"></div>';
        $html .= $this->renderTwoColumnSection('Gerätetyp, Hersteller & Betriebssystem', $deviceCharts, $deviceAndBrandTable);
        $charts[] = ['id' => 'chart_device_type_compact', 'option' => $this->buildTopCategoriesBarOption($browsertype->getData(), '{b}: <b>{c}</b>', 6)];
        $charts[] = ['id' => 'chart_device_brand_compact', 'option' => $this->buildTopCategoriesBarOption($brand->getData(), '{b}: <b>{c}</b>', 7)];
        $charts[] = ['id' => 'chart_device_os_compact', 'option' => $this->buildTopCategoriesBarOption($os->getData(), '{b}: <b>{c}</b>', 7)];

        // Keep model as table-only to reduce chart density and browser load.
        $html .= $this->renderTableOnlySection($this->addon->i18n('statistics_model'), $model->getList());

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_days'), 'chart_weekday', $weekday->getList());
        $charts[] = ['id' => 'chart_weekday', 'option' => $this->buildWeekdayOption($weekday->getData())];

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_hours'), 'chart_hour', $hour->getList());
        $charts[] = ['id' => 'chart_hour', 'option' => $this->buildHourOption($hour->getData())];

        return ['html' => $html, 'charts' => $charts];
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderExtendedBlock(): array
    {
        $pagecount = new Pagecount();
        $visitduration = new VisitDuration();
        $lastpage = new Lastpage();
        $country = new Country();

        $html = '';
        $charts = [];

        $html .= $this->renderVerticalSection(
            'Anzahl besuchter Seiten in einer Sitzung',
            'chart_pagecount',
            $pagecount->getList(),
            'pc_modal',
            '<p>Zeigt an, wie viele Seiten in einer Sitzung besucht wurden.</p>'
        );
        $pagecountData = $pagecount->getChartData();
        $charts[] = ['id' => 'chart_pagecount', 'option' => $this->buildGenericBarOption($pagecountData['values'], $pagecountData['labels'], '{b} Seiten besucht: <b>{c} mal</b>')];

        $html .= $this->renderVerticalSection(
            'Besuchsdauer',
            'chart_visitduration',
            $visitduration->getList(),
            'bd_modal',
            "<p>Zeigt an, wie viel Zeit auf der Webseite verbracht wurde. Ein Wert von genau '0 Sekunden' sagt aus, dass der Besucher nur eine einzige Seite besucht hat.</p> Hinweis: <p>Die Besuchsdauer wird nur annähernd genau erfasst. D.h. konkret, die Besuchszeit der letzten vom Besucher aufgerufenen Seite kann nicht erfasst werden. Die Zeit berechnet sich somit aus der Dauer aller Aufrufe ausgenommen des letzten.</p>"
        );
        $visitdurationData = $visitduration->getChartData();
        $charts[] = ['id' => 'chart_visitduration', 'option' => $this->buildGenericBarOption($visitdurationData['values'], $visitdurationData['labels'], '{b} <br> <b>{c} mal</b>')];

        $lastpageData = $lastpage->getChartData();

        $html .= $this->renderInsightTableSection(
            'Ausstiegsseiten',
            'Top-Ausstiegsseiten im gewählten Zeitraum',
            $lastpageData['labels'],
            $lastpageData['values'],
            $lastpage->getList(),
            '{b} <br> Anzahl: <b>{c}</b>'
        );

        $countryData = $country->getChartData();
        $html .= $this->renderInsightTableSection(
            'Länder',
            'Geografische Verteilung auf einen Blick',
            $countryData['labels'],
            $countryData['values'],
            $country->getList(),
            '{b} <br> Anzahl: <b>{c}</b>'
        );

        return ['html' => $html, 'charts' => $charts];
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderBotsBlock(): array
    {
        $sql = rex_sql::factory();
        $rows = $sql->getArray('SELECT * FROM ' . rex::getTable('pagestats_bot') . ' ORDER BY count DESC');

        if ([] === $rows) {
            $table = rex_view::info($this->addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_bots statistics_table table table-striped table-hover">';
            $table .= '<thead><tr>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_name'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_category'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_producer'), ENT_QUOTES) . '</th>';
            $table .= '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $table .= '<tr>';
                $table .= '<td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES) . '</td>';
                $table .= '<td data-sort="' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '</td>';
                $table .= '<td>' . htmlspecialchars((string) $row['category'], ENT_QUOTES) . '</td>';
                $table .= '<td>' . htmlspecialchars((string) $row['producer'], ENT_QUOTES) . '</td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';
        }

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Bots:');
        $fragment->setVar('body', $table, false);

        return [
            'html' => $fragment->parse('core/page/section.php'),
            'charts' => [],
        ];
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderMainListBlock(string $period): array
    {
        $listData = new ListData($this->filter_date_helper);

        if ('daily' === $period) {
            return ['html' => $listData->getDailyContent(), 'charts' => []];
        }

        if ('monthly' === $period) {
            return ['html' => $listData->getMonthlyContent(), 'charts' => []];
        }

        if ('yearly' === $period) {
            return ['html' => $listData->getYearlyContent(), 'charts' => []];
        }

        throw new \InvalidArgumentException('Unknown main list period: ' . $period);
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderMainChartBlock(string $period): array
    {
        $chartData = new chartData($this->filter_date_helper);

        if ('monthly' === $period) {
            $data = $chartData->getChartDataMonthly();

            return [
                'html' => '',
                'charts' => [[
                    'id' => 'chart_visits_monthly',
                    'option' => $this->buildMainTimelineOption($data),
                ]],
            ];
        }

        if ('yearly' === $period) {
            $data = $chartData->getChartDataYearly();

            return [
                'html' => '',
                'charts' => [[
                    'id' => 'chart_visits_yearly',
                    'option' => $this->buildMainTimelineOption($data),
                ]],
            ];
        }

        throw new \InvalidArgumentException('Unknown main chart period: ' . $period);
    }

    private function renderVerticalSection(string $title, string $chartId, string $table, ?string $modalId = null, ?string $note = null): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', $title);
        $fragment->setVar('chart', '<div id="' . htmlspecialchars($chartId, ENT_QUOTES) . '" style="width: 100%;height:500px"></div>', false);
        $fragment->setVar('table', $table, false);

        if (null !== $modalId && null !== $note) {
            $fragment->setVar('modalid', $modalId, false);
            $fragment->setVar('note', $note, false);
        }

        return $fragment->parse('data_vertical.php');
    }

    private function renderTableOnlySection(string $title, string $table): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', $title);
        $fragment->setVar('body', $table, false);

        return $fragment->parse('core/page/section.php');
    }

    private function renderTwoColumnSection(string $title, string $left, string $right): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', $title);
        $fragment->setVar(
            'body',
            '<div class="row">'
                . '<div class="col-sm-12 col-lg-6">' . $left . '</div>'
                . '<div class="col-sm-12 col-lg-6">' . $right . '</div>'
            . '</div>',
            false
        );

        return $fragment->parse('core/page/section.php');
    }

    /**
     * @param array<int, int|string> $labels
     * @param array<int, int|string> $values
     */
    private function renderInsightTableSection(string $title, string $subtitle, array $labels, array $values, string $table, string $tooltipFormatter): string
    {
        $insights = $this->buildInsightList($labels, $values, $tooltipFormatter, 6);

        $fragment = new rex_fragment();
        $fragment->setVar('title', $title);
        $fragment->setVar(
            'body',
            '<div class="row">'
                . '<div class="col-sm-12 col-lg-5">'
                    . '<div class="alert alert-info" style="margin-bottom:12px;">'
                        . '<strong>' . htmlspecialchars($subtitle, ENT_QUOTES) . '</strong>'
                    . '</div>'
                    . $insights
                . '</div>'
                . '<div class="col-sm-12 col-lg-7">'
                    . $table
                . '</div>'
            . '</div>',
            false
        );

        return $fragment->parse('core/page/section.php');
    }

    /**
     * @param array<int, int|string> $labels
     * @param array<int, int|string> $values
     */
    private function buildInsightList(array $labels, array $values, string $tooltipFormatter, int $limit = 6): string
    {
        if ([] === $labels || [] === $values) {
            return rex_view::info($this->addon->i18n('statistics_no_data'));
        }

        $combined = [];
        foreach ($labels as $index => $label) {
            $combined[] = [
                'label' => (string) $label,
                'value' => isset($values[$index]) ? (int) $values[$index] : 0,
            ];
        }

        usort(
            $combined,
            static fn (array $a, array $b): int => $b['value'] <=> $a['value']
        );

        $top = array_slice($combined, 0, $limit);
        if ([] === $top) {
            return rex_view::info($this->addon->i18n('statistics_no_data'));
        }

        $max = max(array_column($top, 'value'));
        $palette = $this->getChartPalette();

        $html = '<div class="list-group">';
        foreach ($top as $index => $entry) {
            $percentage = $max > 0 ? (int) round(($entry['value'] / $max) * 100) : 0;
            $color = $palette[$index % count($palette)];

            $html .= '<div class="list-group-item" title="' . htmlspecialchars(str_replace(['{b}', '{c}'], [$entry['label'], (string) $entry['value']], $tooltipFormatter), ENT_QUOTES) . '">';
            $html .= '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">';
            $html .= '<span style="word-break:break-word;">' . htmlspecialchars($entry['label'], ENT_QUOTES) . '</span>';
            $html .= '<span class="label" style="background:' . htmlspecialchars($color, ENT_QUOTES) . ';">' . htmlspecialchars((string) $entry['value'], ENT_QUOTES) . '</span>';
            $html .= '</div>';
            $html .= '<div style="margin-top:8px;height:6px;background:#eef2f6;border-radius:999px;overflow:hidden;">';
            $html .= '<div style="width:' . $percentage . '%;height:6px;background:' . htmlspecialchars($color, ENT_QUOTES) . ';"></div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array{name: string, value: int}> $data
     * @return array<string, mixed>
     */
    private function buildTopCategoriesBarOption(array $data, string $tooltipFormatter, int $limit = 10): array
    {
        if ([] === $data) {
            $labels = [];
            $values = [];
        } else {
            $top = array_slice($data, 0, $limit);
            $other = array_slice($data, $limit);

            if ([] !== $other) {
                $otherSum = array_reduce(
                    $other,
                    static fn (int $carry, array $row): int => $carry + (int) $row['value'],
                    0
                );
                $top[] = ['name' => 'Andere', 'value' => $otherSum];
            }

            $labels = array_column($top, 'name');
            $values = array_map(static fn ($value): int => (int) $value, array_column($top, 'value'));
        }

        $coloredValues = $this->buildBarDataWithPalette($values);

        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => $tooltipFormatter,
                'axisPointer' => ['type' => 'shadow'],
            ],
            'grid' => [
                'containLabel' => true,
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'top' => '4%',
            ],
            'toolbox' => $this->buildBarToolbox(),
            'xAxis' => [[
                'type' => 'value',
            ]],
            'series' => [[
                'name' => 'Anzahl',
                'type' => 'bar',
                'data' => $coloredValues,
                'label' => [
                    'show' => true,
                    'position' => 'right',
                    'formatter' => '{c}',
                ],
                'barMaxWidth' => 28,
                'showBackground' => true,
                'itemStyle' => [
                    'borderRadius' => [0, 8, 8, 0],
                ],
                'emphasis' => [
                    'itemStyle' => [
                        'shadowBlur' => 10,
                        'shadowOffsetX' => 0,
                        'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                    ],
                ],
            ]],
            'yAxis' => [[
                'type' => 'category',
                'data' => $labels,
                'inverse' => true,
                'axisTick' => ['show' => false],
            ]],
            'dataZoom' => [[
                'type' => 'inside',
                'yAxisIndex' => 0,
                'filterMode' => 'none',
            ]],
        ];
    }

    /**
     * @param array<int, array{name: string, value: int}> $deviceTypeData
     * @param array<int, array{name: string, value: int}> $brandData
     * @return array<string, mixed>
     */
    private function buildDeviceBrandOsStackedOption(array $deviceTypeData, array $brandData, array $osData, int $brandLimit = 7, int $osLimit = 7): array
    {
        $deviceTop = $this->reduceToTopAndOther($deviceTypeData, 6);
        $brandTop = $this->reduceToTopAndOther($brandData, $brandLimit);
        $osTop = $this->reduceToTopAndOther($osData, $osLimit);

        $seriesMap = [];

        foreach ($deviceTop as $row) {
            $name = (string) $row['name'];
            $seriesMap[$name] = [
                'name' => $name,
                'type' => 'bar',
                'stack' => 'gesamt',
                'emphasis' => ['focus' => 'series'],
                'data' => [(int) $row['value'], 0, 0],
            ];
        }

        foreach ($brandTop as $row) {
            $name = (string) $row['name'];
            if (!isset($seriesMap[$name])) {
                $seriesMap[$name] = [
                    'name' => $name,
                    'type' => 'bar',
                    'stack' => 'gesamt',
                    'emphasis' => ['focus' => 'series'],
                    'data' => [0, (int) $row['value'], 0],
                ];
                continue;
            }

            $seriesMap[$name]['data'][1] = (int) $row['value'];
        }

        foreach ($osTop as $row) {
            $name = (string) $row['name'];
            if (!isset($seriesMap[$name])) {
                $seriesMap[$name] = [
                    'name' => $name,
                    'type' => 'bar',
                    'stack' => 'gesamt',
                    'emphasis' => ['focus' => 'series'],
                    'data' => [0, 0, (int) $row['value']],
                ];
                continue;
            }

            $seriesMap[$name]['data'][2] = (int) $row['value'];
        }

        $series = array_values($seriesMap);
        $palette = $this->getChartPalette();
        foreach ($series as $index => &$entry) {
            $entry['data'] = array_map(static fn ($value): float => (float) $value, $entry['data']);
            $entry['itemStyle'] = [
                'color' => $palette[$index % count($palette)],
                'borderRadius' => [3, 3, 3, 3],
            ];
            $entry['label'] = [
                'show' => true,
                'position' => 'inside',
                'formatter' => '{c}%',
            ];
        }
        unset($entry);

        $totals = [0.0, 0.0, 0.0];
        foreach ($series as $entry) {
            $totals[0] += (float) $entry['data'][0];
            $totals[1] += (float) $entry['data'][1];
            $totals[2] += (float) $entry['data'][2];
        }

        foreach ($series as &$entry) {
            $entry['data'] = [
                $totals[0] > 0 ? round((((float) $entry['data'][0]) / $totals[0]) * 100, 1) : 0,
                $totals[1] > 0 ? round((((float) $entry['data'][1]) / $totals[1]) * 100, 1) : 0,
                $totals[2] > 0 ? round((((float) $entry['data'][2]) / $totals[2]) * 100, 1) : 0,
            ];
        }
        unset($entry);

        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
                'valueFormatter' => '{value}%',
            ],
            'legend' => [
                'show' => false,
            ],
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'top' => '8%',
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'value',
                'max' => 100,
                'axisLabel' => [
                    'formatter' => '{value}%',
                ],
            ],
            'yAxis' => [
                'type' => 'category',
                'data' => [$this->addon->i18n('statistics_devicetype'), $this->addon->i18n('statistics_brand'), $this->addon->i18n('statistics_os')],
                'axisTick' => ['show' => false],
            ],
            'toolbox' => $this->buildBarToolbox(),
            'series' => $series,
        ];
    }

    /**
     * @param array<int, array{name: string, value: int}> $rows
     * @return array<int, array{name: string, value: int}>
     */
    private function reduceToTopAndOther(array $rows, int $limit): array
    {
        if ([] === $rows) {
            return [];
        }

        $top = array_slice($rows, 0, $limit);
        $other = array_slice($rows, $limit);

        if ([] !== $other) {
            $otherSum = array_reduce(
                $other,
                static fn (int $carry, array $row): int => $carry + (int) $row['value'],
                0
            );
            $top[] = ['name' => 'Andere', 'value' => $otherSum];
        }

        return $top;
    }

    /**
     * @param array<int, int> $values
     * @return array<int, array{value: int, itemStyle: array{color: string}}>
     */
    private function buildBarDataWithPalette(array $values): array
    {
        $palette = $this->getChartPalette();
        $rows = [];

        foreach ($values as $index => $value) {
            $rows[] = [
                'value' => (int) $value,
                'itemStyle' => [
                    'color' => $palette[$index % count($palette)],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function getChartPalette(): array
    {
        return [
            '#1f77b4',
            '#4e79a7',
            '#59a14f',
            '#9c755f',
            '#edc948',
            '#f28e2b',
            '#e15759',
            '#76b7b2',
            '#b07aa1',
            '#ff9da7',
            '#86bcb6',
            '#a0cbe8',
        ];
    }

    /**
     * @param array<int, int> $data
     * @return array<string, mixed>
     */
    private function buildWeekdayOption(array $data): array
    {
        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => '{b}: <b>{c}</b>',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'grid' => [
                'containLabel' => true,
                'left' => '3%',
                'right' => '3%',
                'bottom' => '3%',
            ],
            'xAxis' => [[
                'type' => 'category',
                'data' => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
                'axisTick' => ['alignWithLabel' => true],
            ]],
            'yAxis' => [[
                'type' => 'value',
            ]],
            'toolbox' => $this->buildBarToolbox(),
            'series' => [[
                'type' => 'bar',
                'data' => $data,
                'label' => ['show' => false],
                'emphasis' => [
                    'itemStyle' => [
                        'shadowBlur' => 10,
                        'shadowOffsetX' => 0,
                        'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                    ],
                ],
            ]],
        ];
    }

    /**
     * @param array<int, int> $data
     * @return array<string, mixed>
     */
    private function buildHourOption(array $data): array
    {
        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => '{b} Uhr: <b>{c}</b>',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'grid' => [
                'containLabel' => true,
                'left' => '3%',
                'right' => '3%',
                'bottom' => '3%',
            ],
            'xAxis' => [[
                'type' => 'category',
                'data' => ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'],
                'axisTick' => ['alignWithLabel' => true],
            ]],
            'yAxis' => [[
                'type' => 'value',
            ]],
            'toolbox' => $this->buildBarToolbox(),
            'series' => [[
                'type' => 'bar',
                'data' => $data,
                'label' => ['show' => false],
                'emphasis' => [
                    'itemStyle' => [
                        'shadowBlur' => 10,
                        'shadowOffsetX' => 0,
                        'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                    ],
                ],
            ]],
        ];
    }

    /**
     * @param array<int, int|string> $categories
     * @param array<int, int|string> $values
     * @return array<string, mixed>
     */
    private function buildGenericBarOption(array $categories, array $values, string $tooltipFormatter): array
    {
        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => $tooltipFormatter,
                'axisPointer' => ['type' => 'shadow'],
            ],
            'grid' => [
                'containLabel' => true,
                'left' => '3%',
                'right' => '3%',
                'bottom' => '3%',
            ],
            'xAxis' => [[
                'type' => 'category',
                'data' => $categories,
                'axisTick' => ['alignWithLabel' => true],
            ]],
            'yAxis' => [[
                'type' => 'value',
            ]],
            'toolbox' => $this->buildBarToolbox(),
            'series' => [[
                'type' => 'bar',
                'data' => $values,
                'label' => ['show' => false],
                'emphasis' => [
                    'itemStyle' => [
                        'shadowBlur' => 10,
                        'shadowOffsetX' => 0,
                        'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                    ],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBarToolbox(): array
    {
        return [
            'show' => $this->showToolbox(),
            'orient' => 'vertical',
            'top' => '10%',
            'feature' => [
                'dataZoom' => ['yAxisIndex' => 'none'],
                'dataView' => ['readOnly' => false],
                'magicType' => ['type' => ['line', 'bar']],
                'restore' => (object) [],
                'saveAsImage' => (object) [],
            ],
        ];
    }

    /**
     * @param array{legend: array<int, string>, xaxis: array<int, string>, series: array<int, array<string, mixed>>} $data
     * @return array<string, mixed>
     */
    private function buildMainTimelineOption(array $data): array
    {
        $series = array_map(function (array $entry): array {
            $name = (string) ($entry['name'] ?? '');

            $entry['type'] = 'line';
            $entry['smooth'] = true;
            $entry['showSymbol'] = false;
            $entry['sampling'] = 'lttb';

            $isTotal = false !== strpos($name, 'Gesamt');
            if (!$isTotal && 0 === strpos($name, 'Aufrufe ')) {
                $entry['stack'] = 'Aufrufe';
                $entry['areaStyle'] = ['opacity' => 0.1];
            }

            if (!$isTotal && 0 === strpos($name, 'Besucher ')) {
                $entry['stack'] = 'Besucher';
                $entry['areaStyle'] = ['opacity' => 0.08];
            }

            if ($isTotal) {
                $entry['lineStyle'] = ['width' => 3];
                $entry['z'] = 10;
            }

            return $entry;
        }, $data['series']);

        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
            ],
            'dataZoom' => [[
                'id' => 'dataZoomX',
                'type' => 'slider',
                'xAxisIndex' => [0],
                'filterMode' => 'filter',
            ]],
            'grid' => [
                'left' => '5%',
                'right' => '5%',
            ],
            'toolbox' => [
                'show' => $this->showToolbox(),
                'orient' => 'vertical',
                'top' => '10%',
                'feature' => [
                    'dataZoom' => ['yAxisIndex' => 'none'],
                    'dataView' => ['readOnly' => false],
                    'magicType' => ['type' => ['line', 'bar', 'stack']],
                    'restore' => (object) [],
                    'saveAsImage' => (object) [],
                ],
            ],
            'legend' => [
                'data' => $data['legend'],
                'right' => '5%',
                'type' => 'scroll',
            ],
            'xAxis' => [
                'data' => $data['xaxis'],
                'type' => 'category',
                'boundaryGap' => false,
            ],
            'yAxis' => (object) [],
            'series' => $series,
        ];
    }

    private function showToolbox(): bool
    {
        return (bool) rex_config::get('statistics', 'statistics_show_chart_toolbox');
    }
}