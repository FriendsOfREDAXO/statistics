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

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_devicetype'), 'chart_browsertype', $browsertype->getList());
        $charts[] = ['id' => 'chart_browsertype', 'option' => $this->buildTopCategoriesBarOption($browsertype->getData(), '{b}: <b>{c}</b>', 6)];

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_os'), 'chart_os', $os->getList());
        $charts[] = ['id' => 'chart_os', 'option' => $this->buildTopCategoriesBarOption($os->getData(), '{b}: <b>{c}</b>')];

        $html .= $this->renderVerticalSection($this->addon->i18n('statistics_brand'), 'chart_brand', $brand->getList());
        $charts[] = ['id' => 'chart_brand', 'option' => $this->buildTopCategoriesBarOption($brand->getData(), '{b}: <b>{c}</b>')];

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

        $html .= $this->renderVerticalSection(
            'Ausstiegsseiten',
            'chart_lastpage',
            $lastpage->getList(),
            'lp_modal',
            '<p>Zeigt an, welche URLs als letztes aufgerufen worden sind bevor die Webseite verlassen wurde.</p>'
        );
        $lastpageData = $lastpage->getChartData();
        $charts[] = ['id' => 'chart_lastpage', 'option' => $this->buildGenericBarOption($lastpageData['labels'], $lastpageData['values'], '{b} <br> Anzahl: <b>{c}</b>')];

        $html .= $this->renderVerticalSection('Länder', 'chart_country', $country->getList());
        $countryData = $country->getChartData();
        $charts[] = ['id' => 'chart_country', 'option' => $this->buildGenericBarOption($countryData['labels'], $countryData['values'], '{b} <br> Anzahl: <b>{c}</b>')];

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
                'data' => $values,
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