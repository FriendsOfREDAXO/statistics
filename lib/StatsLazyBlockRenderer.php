<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_addon_interface;
use rex_config;
use rex_fragment;
use rex_sql;
use rex_view;

class StatsLazyBlockRenderer
{
    private DateFilter $filter_date_helper;
    private rex_addon_interface $addon;

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

        if ('device-hourly' === $blockId) {
            return $this->renderDeviceSubBlock($blockId);
        }

        if ('extended' === $blockId) {
            return $this->renderExtendedBlock();
        }

        if (in_array($blockId, ['extended-pagecount', 'extended-visitduration', 'extended-lastpage', 'extended-country'], true)) {
            return $this->renderExtendedSubBlock($blockId);
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

        $html = '';
        $charts = [];

        $html .= '<div class="alert alert-info" style="margin-bottom:10px;">'
            . htmlspecialchars($this->addon->i18n('statistics_filter_scope_alltime_data'), ENT_QUOTES)
            . '</div>';

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

        $html .= $this->renderWeekdayHeatmapSection($this->addon->i18n('statistics_days'), $weekday->getData(), $weekday->getList());

        $html .= $this->renderLazySectionCard(
            $this->addon->i18n('statistics_hours'),
            'Wird bei Bedarf geladen und nutzt mehr Platz für den Verlauf.',
            'device-hourly'
        );

        return ['html' => $html, 'charts' => $charts];
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderDeviceSubBlock(string $blockId): array
    {
        if ('device-hourly' === $blockId) {
            $hour = new Hour();

            return [
                'html' => $this->renderHourlyBarsSectionWide(
                    $this->addon->i18n('statistics_hours'),
                    $hour->getData(),
                    $hour->getList()
                ),
                'charts' => [],
            ];
        }

        throw new \InvalidArgumentException('Unknown device sub-block id: ' . $blockId);
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderExtendedBlock(): array
    {
        $html = '';

        $html .= $this->renderLazySectionCard(
            'Anzahl besuchter Seiten in einer Sitzung',
            'Wird bei Bedarf geladen, um den Browser zu entlasten.',
            'extended-pagecount'
        );
        $html .= $this->renderLazySectionCard(
            'Besuchsdauer',
            'Wird bei Bedarf geladen, um den Browser zu entlasten.',
            'extended-visitduration'
        );
        $html .= $this->renderLazySectionCard(
            'Ausstiegsseiten',
            'Wird bei Bedarf geladen, um den Browser zu entlasten.',
            'extended-lastpage'
        );
        $html .= $this->renderLazySectionCard(
            'Länder',
            'Wird bei Bedarf geladen, um den Browser zu entlasten.',
            'extended-country'
        );

        return ['html' => $html, 'charts' => []];
    }

    /**
     * @return array{html: string, charts: array<int, array{id: string, option: array<string, mixed>}>}
     */
    private function renderExtendedSubBlock(string $blockId): array
    {
        if ('extended-pagecount' === $blockId) {
            $pagecount = new Pagecount($this->filter_date_helper);
            $pagecountData = $pagecount->getChartData();
            $labels = [];
            $values = [];
            foreach ($pagecountData['values'] as $index => $pagesVisited) {
                $labels[] = (string) $pagesVisited . ' Seiten';
                $values[] = isset($pagecountData['labels'][$index]) ? (int) $pagecountData['labels'][$index] : 0;
            }

            return [
                'html' => $this->renderInsightTableSection(
                    'Anzahl besuchter Seiten in einer Sitzung',
                    'Verteilung der Seiten pro Sitzung',
                    $labels,
                    $values,
                    $pagecount->getList(),
                    '{b}: <b>{c} Sitzungen</b>'
                ),
                'charts' => [],
            ];
        }

        if ('extended-visitduration' === $blockId) {
            $visitduration = new VisitDuration($this->filter_date_helper);
            $visitdurationData = $visitduration->getChartData();
            $labels = [];
            $values = [];
            foreach ($visitdurationData['values'] as $index => $timespan) {
                $labels[] = (string) $timespan;
                $values[] = isset($visitdurationData['labels'][$index]) ? (int) $visitdurationData['labels'][$index] : 0;
            }

            return [
                'html' => $this->renderInsightTableSection(
                    'Besuchsdauer',
                    'Verteilung der Sitzungsdauer',
                    $labels,
                    $values,
                    $visitduration->getList(),
                    '{b}: <b>{c} Sitzungen</b>'
                ),
                'charts' => [],
            ];
        }

        if ('extended-lastpage' === $blockId) {
            $lastpage = new Lastpage($this->filter_date_helper);
            $lastpageData = $lastpage->getChartData();

            return [
                'html' => $this->renderInsightTableSection(
                    'Ausstiegsseiten',
                    'Top-Ausstiegsseiten im gewählten Zeitraum',
                    $lastpageData['labels'],
                    $lastpageData['values'],
                    $lastpage->getList(),
                    '{b} <br> Anzahl: <b>{c}</b>'
                ),
                'charts' => [],
            ];
        }

        if ('extended-country' === $blockId) {
            $country = new Country();
            $countryData = $country->getChartData();

            $note = '<div class="alert alert-info" style="margin-bottom:10px;">'
                . htmlspecialchars($this->addon->i18n('statistics_filter_scope_alltime_data'), ENT_QUOTES)
                . '</div>';

            return [
                'html' => $note . $this->renderInsightTableSection(
                    'Länder',
                    'Geografische Verteilung auf einen Blick',
                    $countryData['labels'],
                    $countryData['values'],
                    $country->getList(),
                    '{b} <br> Anzahl: <b>{c}</b>'
                ),
                'charts' => [],
            ];
        }

        throw new \InvalidArgumentException('Unknown extended sub-block id: ' . $blockId);
    }

    private function renderLazySectionCard(string $title, string $description, string $lazyBlockId): string
    {
        $collapseId = 'statistics-collapse-' . md5($lazyBlockId . '-' . (string) random_int(1000, 9999));

        $body = '<div class="panel panel-default">'
            . '<div class="panel-heading">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">'
                    . '<strong>' . htmlspecialchars($title, ENT_QUOTES) . '</strong>'
                    . '<button class="btn btn-primary btn-xs" type="button" data-toggle="collapse" data-target="#' . htmlspecialchars($collapseId, ENT_QUOTES) . '">' . htmlspecialchars($this->addon->i18n('statistics_toggle_collapse_table'), ENT_QUOTES) . '</button>'
                . '</div>'
                . '<div style="margin-top:6px;color:#708090;">' . htmlspecialchars($description, ENT_QUOTES) . '</div>'
            . '</div>'
            . '<div id="' . htmlspecialchars($collapseId, ENT_QUOTES) . '" class="collapse">'
                . '<div class="panel-body">'
                    . '<div data-statistics-lazy-collapse data-block-id="' . htmlspecialchars($lazyBlockId, ENT_QUOTES) . '" data-date-start="' . htmlspecialchars($this->filter_date_helper->date_start->format('Y-m-d'), ENT_QUOTES) . '" data-date-end="' . htmlspecialchars($this->filter_date_helper->date_end->format('Y-m-d'), ENT_QUOTES) . '" data-state="idle"></div>'
                . '</div>'
            . '</div>'
        . '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('class', 'default', false);
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', $body, false);

        return $fragment->parse('core/page/section.php');
    }

    /**
     * @param array<int, int> $data
     */
    private function renderWeekdayHeatmapSection(string $title, array $data, string $table): string
    {
        $labels = [
            $this->addon->i18n('statistics_monday'),
            $this->addon->i18n('statistics_tuesday'),
            $this->addon->i18n('statistics_wednesday'),
            $this->addon->i18n('statistics_thursday'),
            $this->addon->i18n('statistics_friday'),
            $this->addon->i18n('statistics_saturday'),
            $this->addon->i18n('statistics_sunday'),
        ];

        $max = [] === $data ? 0 : max($data);
        $max = $max > 0 ? $max : 1;

        $cards = '<div class="row">';
        foreach ($labels as $index => $label) {
            $value = isset($data[$index]) ? (int) $data[$index] : 0;
            $ratio = $value / $max;
            $lightness = 95 - (int) round($ratio * 45);
            $bg = 'hsl(205, 72%, ' . $lightness . '%)';

            $cards .= '<div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:10px;">';
            $cards .= '<div style="border:1px solid #d9e2ec;border-radius:8px;padding:10px;background:' . $bg . ';">';
            $cards .= '<div style="font-size:12px;opacity:.85;">' . htmlspecialchars($label, ENT_QUOTES) . '</div>';
            $cards .= '<div style="font-size:20px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) $value, ENT_QUOTES) . '</div>';
            $cards .= '</div></div>';
        }
        $cards .= '</div>';

        return $this->renderTwoColumnSection($title, $cards, $table);
    }

    /**
     * @param array<int, int> $data
     */
    protected function renderHourlyBarsSection(string $title, array $data, string $table): string
    {
        $max = [] === $data ? 0 : max($data);
        $max = $max > 0 ? $max : 1;
        $palette = $this->getChartPalette();

        $bars = '<div style="padding:8px 8px 2px;border:1px solid #dfe7ef;border-radius:8px;background:#fff;">';
        $bars .= '<div style="display:flex;align-items:flex-end;gap:4px;height:130px;overflow-x:auto;padding-bottom:8px;">';

        for ($hour = 0; $hour < 24; ++$hour) {
            $value = isset($data[$hour]) ? (int) $data[$hour] : 0;
            $height = 8 + (int) round(($value / $max) * 108);
            $color = $palette[$hour % count($palette)];

            $bars .= '<div title="' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00 - ' . htmlspecialchars((string) $value, ENT_QUOTES) . '" style="min-width:18px;text-align:center;">';
            $bars .= '<div style="height:' . $height . 'px;background:' . htmlspecialchars($color, ENT_QUOTES) . ';border-radius:4px 4px 0 0;"></div>';
            $bars .= '<div style="font-size:10px;color:#6b7c93;">' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . '</div>';
            $bars .= '</div>';
        }

        $bars .= '</div>';
        $bars .= '<div style="font-size:12px;color:#6b7c93;padding:4px 2px 0;">Stundenverteilung im Tagesverlauf</div>';
        $bars .= '</div>';

        return $this->renderTwoColumnSection($title, $bars, $table);
    }

    /**
     * @param array<int, int> $data
     */
    private function renderHourlyBarsSectionWide(string $title, array $data, string $table): string
    {
        $max = [] === $data ? 0 : max($data);
        $max = $max > 0 ? $max : 1;
        $palette = $this->getChartPalette();
        $tableCollapseId = 'statistics-hourly-table-' . md5((string) random_int(1000, 9999));

        $bars = '<div style="padding:14px 14px 8px;border:1px solid #dfe7ef;border-radius:8px;background:#fff;">';
        $bars .= '<div style="display:flex;align-items:flex-end;gap:6px;height:190px;overflow-x:auto;padding-bottom:10px;">';

        for ($hour = 0; $hour < 24; ++$hour) {
            $value = isset($data[$hour]) ? (int) $data[$hour] : 0;
            $height = 12 + (int) round(($value / $max) * 158);
            $color = $palette[$hour % count($palette)];

            $bars .= '<div title="' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00 - ' . htmlspecialchars((string) $value, ENT_QUOTES) . '" style="flex:1 1 0;min-width:24px;max-width:56px;text-align:center;">';
            $bars .= '<div style="height:' . $height . 'px;background:' . htmlspecialchars($color, ENT_QUOTES) . ';border-radius:5px 5px 0 0;"></div>';
            $bars .= '<div style="font-size:11px;color:#6b7c93;margin-top:2px;">' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . '</div>';
            $bars .= '</div>';
        }

        $bars .= '</div>';
        $bars .= '<div style="font-size:13px;color:#6b7c93;padding:4px 2px 0;">Stundenverteilung im Tagesverlauf</div>';
        $bars .= '</div>';

        $bars .= '<div style="margin-top:12px;">';
        $bars .= '<button class="btn btn-default btn-xs" type="button" data-toggle="collapse" data-target="#' . htmlspecialchars($tableCollapseId, ENT_QUOTES) . '">';
        $bars .= htmlspecialchars($this->addon->i18n('statistics_toggle_collapse_table'), ENT_QUOTES);
        $bars .= '</button>';
        $bars .= '<div id="' . htmlspecialchars($tableCollapseId, ENT_QUOTES) . '" class="collapse" style="margin-top:10px;">' . $table . '</div>';
        $bars .= '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $title);
        $fragment->setVar('body', $bars, false);

        return $fragment->parse('core/page/section.php');
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
    /**
     * @param array<int, array{name: string, value: int}> $deviceTypeData
     * @param array<int, array{name: string, value: int}> $brandData
     * @param array<int, array{name: string, value: int}> $osData
     * @return array<string, mixed>
     */
    protected function buildDeviceBrandOsStackedOption(array $deviceTypeData, array $brandData, array $osData, int $brandLimit = 7, int $osLimit = 7): array
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
    protected function buildWeekdayOption(array $data): array
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
    protected function buildHourOption(array $data): array
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
    protected function buildGenericBarOption(array $categories, array $values, string $tooltipFormatter): array
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
    * @param array{legend: array<int, mixed>, xaxis: array<int, string>, series: array<int, array<string, mixed>>} $data
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