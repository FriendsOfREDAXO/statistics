<?php

namespace AndiLeni\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use rex;
use rex_addon;
use rex_sql;

class ReportPdfGenerator
{
    private rex_addon $addon;

    public function __construct()
    {
        $this->addon = rex_addon::get('statistics');
    }

    /**
     * @param string $periodType week|month|year
     */
    public function generate(string $periodType, string $periodValue): void
    {
        [$start, $end, $label] = $this->resolvePeriod($periodType, $periodValue);

        $kpi = $this->loadKpiData($start, $end);
        $dailyVisits = $this->loadDailyVisits($start, $end);
        $deviceTypes = $this->loadDataTypeList('browsertype', 8);
        $topPages = $this->loadTopList('pagestats_visits_per_url', 'url', $start, $end, 20);
        $topReferers = $this->loadTopList('pagestats_referer', 'referer', $start, $end, 20);

        $title = $this->addon->i18n('statistics_report_title');
        $filename = \rex_string::normalize('statistics_' . $periodType . '_' . $start->format('Y-m-d'));
        $html = $this->renderHtml($title, $label, $start, $end, $kpi, $dailyVisits, $deviceTypes, $topPages, $topReferers);

        $pdfClass = 'FriendsOfRedaxo\\PdfOut\\PdfOut';
        if (!class_exists($pdfClass)) {
            throw new InvalidArgumentException('PdfOut class not available');
        }

        $pdf = new $pdfClass();
        $pdf->setName($filename)
            ->setPaperSize('A4', 'portrait')
            ->setAttachment(true)
            ->setHtml($html)
            ->run();
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string}
     */
    private function resolvePeriod(string $periodType, string $periodValue): array
    {
        if ('year' === $periodType) {
            if (!preg_match('/^\\d{4}$/', $periodValue)) {
                throw new InvalidArgumentException('Invalid year format');
            }

            $start = new DateTimeImmutable($periodValue . '-01-01');
            $end = new DateTimeImmutable($periodValue . '-12-31');

            return [$start, $end, $this->addon->i18n('statistics_report_period_year') . ': ' . $periodValue];
        }

        if ('month' === $periodType) {
            if (!preg_match('/^\\d{4}-\\d{2}$/', $periodValue)) {
                throw new InvalidArgumentException('Invalid month format');
            }

            $start = new DateTimeImmutable($periodValue . '-01');
            $end = $start->modify('last day of this month');

            return [$start, $end, $this->addon->i18n('statistics_report_period_month') . ': ' . $start->format('m.Y')];
        }

        if ('week' === $periodType) {
            if (!preg_match('/^\\d{4}-W\\d{2}$/', $periodValue)) {
                throw new InvalidArgumentException('Invalid week format');
            }

            [$year, $week] = explode('-W', $periodValue);
            $start = (new DateTimeImmutable())->setISODate((int) $year, (int) $week)->setTime(0, 0, 0);
            $end = $start->modify('+6 days');

            return [$start, $end, $this->addon->i18n('statistics_report_period_week') . ': KW ' . $week . '/' . $year];
        }

        throw new InvalidArgumentException('Invalid period type');
    }

    /**
     * @return array{visits:int, visitors:int, pagesPerSession:float, activeDays:int, topPage:string, topPageCount:int, topReferer:string, topRefererCount:int}
     */
    private function loadKpiData(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = rex_sql::factory();

        $visits = (int) ($sql->getArray(
            'SELECT IFNULL(SUM(count),0) AS total FROM ' . rex::getTable('pagestats_visits_per_day') . ' WHERE date BETWEEN :start AND :end',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        )[0]['total'] ?? 0);

        $visitors = (int) ($sql->getArray(
            'SELECT IFNULL(SUM(count),0) AS total FROM ' . rex::getTable('pagestats_visitors_per_day') . ' WHERE date BETWEEN :start AND :end',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        )[0]['total'] ?? 0);

        $activeDays = (int) ($sql->getArray(
            'SELECT COUNT(*) AS total FROM ' . rex::getTable('pagestats_visits_per_day') . ' WHERE date BETWEEN :start AND :end AND count > 0',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        )[0]['total'] ?? 0);

        $topPageRow = $sql->getArray(
            'SELECT url AS item, SUM(count) AS total'
            . ' FROM ' . rex::getTable('pagestats_visits_per_url')
            . ' WHERE date BETWEEN :start AND :end'
            . ' GROUP BY url'
            . ' ORDER BY total DESC'
            . ' LIMIT 1',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        $topRefererRow = $sql->getArray(
            'SELECT referer AS item, SUM(count) AS total'
            . ' FROM ' . rex::getTable('pagestats_referer')
            . ' WHERE date BETWEEN :start AND :end'
            . ' GROUP BY referer'
            . ' ORDER BY total DESC'
            . ' LIMIT 1',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        return [
            'visits' => $visits,
            'visitors' => $visitors,
            'pagesPerSession' => $visitors > 0 ? round($visits / $visitors, 2) : 0.0,
            'activeDays' => $activeDays,
            'topPage' => $this->normalizeTopItem((string) ($topPageRow[0]['item'] ?? ''), true),
            'topPageCount' => (int) ($topPageRow[0]['total'] ?? 0),
            'topReferer' => $this->normalizeTopItem((string) ($topRefererRow[0]['item'] ?? ''), false),
            'topRefererCount' => (int) ($topRefererRow[0]['total'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{item:string,count:int}>
     */
    private function loadTopList(string $tableSuffix, string $column, DateTimeImmutable $start, DateTimeImmutable $end, int $limit): array
    {
        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT ' . $column . ' AS item, SUM(count) AS total'
            . ' FROM ' . rex::getTable($tableSuffix)
            . ' WHERE date BETWEEN :start AND :end'
            . ' GROUP BY ' . $column
            . ' ORDER BY total DESC'
            . ' LIMIT ' . (int) $limit,
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        $isPageList = 'url' === $column;

        return array_map(function (array $row) use ($isPageList): array {
            return [
                'item' => $this->normalizeTopItem((string) ($row['item'] ?? ''), $isPageList),
                'count' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }

    private function normalizeTopItem(string $value, bool $isPage): string
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return $isPage
                ? $this->addon->i18n('statistics_report_not_available')
                : $this->addon->i18n('statistics_report_referer_direct');
        }

        return $trimmed;
    }

    /**
     * @return array<int, array{item:string,count:int}>
     */
    private function loadDataTypeList(string $type, int $limit): array
    {
        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT name AS item, IFNULL(count,0) AS total'
            . ' FROM ' . rex::getTable('pagestats_data')
            . ' WHERE type = :type'
            . ' ORDER BY total DESC'
            . ' LIMIT ' . (int) $limit,
            ['type' => $type]
        );

        return array_map(function (array $row): array {
            $item = trim((string) ($row['item'] ?? ''));
            if ('' === $item) {
                $item = $this->addon->i18n('statistics_report_unknown_label');
            }

            return [
                'item' => $item,
                'count' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{date:string,count:int}>
     */
    private function loadDailyVisits(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT date, IFNULL(count,0) AS total'
            . ' FROM ' . rex::getTable('pagestats_visits_per_day')
            . ' WHERE date BETWEEN :start AND :end'
            . ' ORDER BY date ASC',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        return array_map(static function (array $row): array {
            return [
                'date' => (string) ($row['date'] ?? ''),
                'count' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @param array{visits:int, visitors:int, pagesPerSession:float, activeDays:int, topPage:string, topPageCount:int, topReferer:string, topRefererCount:int} $kpi
     * @param array<int, array{date:string,count:int}> $dailyVisits
    * @param array<int, array{item:string,count:int}> $deviceTypes
     * @param array<int, array{item:string,count:int}> $topPages
     * @param array<int, array{item:string,count:int}> $topReferers
     */
    private function renderHtml(
        string $title,
        string $periodLabel,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $kpi,
        array $dailyVisits,
        array $deviceTypes,
        array $topPages,
        array $topReferers
    ): string {
        $generated = date('d.m.Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body{font-family:DejaVu Sans,sans-serif;color:#1e293b;font-size:12px;line-height:1.45;margin:22px;}';
        $html .= '.header{padding:14px 16px;border-radius:10px;background:#e8f3ff;border:1px solid #c6ddff;margin-bottom:16px;}';
        $html .= '.title{font-size:22px;font-weight:700;margin:0 0 4px 0;}';
        $html .= '.sub{font-size:12px;color:#475569;margin:0;}';
        $html .= '.kpis{width:100%;border-collapse:separate;border-spacing:10px 10px;margin:0 0 16px 0;}';
        $html .= '.kpi{width:25%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;vertical-align:top;}';
        $html .= '.klabel{font-size:11px;color:#64748b;margin-bottom:4px;}';
        $html .= '.kvalue{font-size:20px;font-weight:700;color:#0f172a;}';
        $html .= '.kmeta{font-size:11px;color:#334155;margin-top:4px;}';
        $html .= '.section{margin-top:10px;}';
        $html .= '.section h2{font-size:16px;margin:0 0 8px 0;padding-bottom:4px;border-bottom:1px solid #e2e8f0;}';
        $html .= '.graphics-grid{display:flex;gap:12px;align-items:stretch;margin:12px 0 6px 0;}';
        $html .= '.graphics-card{flex:1;border:1px solid #e2e8f0;background:#fbfdff;border-radius:10px;padding:10px;}';
        $html .= '.graphics-card-full{flex:1 1 100%;}';
        $html .= '.graphics-card h3{margin:0 0 8px 0;font-size:13px;color:#334155;}';
        $html .= '.legend{font-size:10px;color:#475569;margin:0 0 8px 0;}';
        $html .= '.legend-item{display:inline-block;margin-right:12px;}';
        $html .= '.legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;vertical-align:middle;}';
        $html .= '.compare-row{margin-bottom:9px;}';
        $html .= '.compare-label{font-size:11px;color:#475569;margin-bottom:4px;}';
        $html .= '.compare-track{background:#e8edf3;height:11px;border-radius:999px;overflow:hidden;}';
        $html .= '.compare-fill{height:11px;border-radius:999px;background:linear-gradient(90deg,#2f80c8 0%,#5ea9ea 100%);}';
        $html .= '.compare-fill-alt{height:11px;border-radius:999px;background:linear-gradient(90deg,#20a39e 0%,#61c7c2 100%);}';
        $html .= '.hbar-table{width:100%;border-collapse:collapse;}';
        $html .= '.hbar-table td{padding:4px 0;vertical-align:middle;}';
        $html .= '.hbar-label{width:34%;font-size:10px;color:#334155;padding-right:8px;word-break:break-word;}';
        $html .= '.hbar-track-cell{width:50%;}';
        $html .= '.hbar-track{height:8px;border-radius:999px;background:#e8edf3;overflow:hidden;}';
        $html .= '.hbar-fill{height:8px;border-radius:999px;background:#2f80c8;}';
        $html .= '.hbar-count{width:16%;text-align:right;font-size:10px;color:#334155;padding-left:8px;white-space:nowrap;}';
        $html .= '.chart-note{font-size:10px;color:#64748b;margin:4px 0 8px 0;}';
        $html .= 'table.list{width:100%;border-collapse:collapse;}';
        $html .= 'table.list th, table.list td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;vertical-align:top;}';
        $html .= 'table.list th{background:#f1f5f9;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#475569;}';
        $html .= 'table.list td.num{text-align:right;white-space:nowrap;width:80px;}';
        $html .= '.muted{color:#64748b;font-size:11px;}';
        $html .= '.small{font-size:11px;color:#475569;}';
        $html .= '</style></head><body>';

        $html .= '<div class="header">';
        $html .= '<p class="title">' . htmlspecialchars($title, ENT_QUOTES) . '</p>';
        $html .= '<p class="sub">' . htmlspecialchars($periodLabel, ENT_QUOTES) . ' | '
            . htmlspecialchars($start->format('d.m.Y') . ' - ' . $end->format('d.m.Y'), ENT_QUOTES)
            . '</p>';
        $html .= '<p class="sub">' . htmlspecialchars($this->addon->i18n('statistics_report_generated_at') . ': ' . $generated, ENT_QUOTES) . '</p>';
        $html .= '</div>';

        $html .= '<table class="kpis"><tr>';
        $html .= $this->renderKpiCard($this->addon->i18n('statistics_report_kpi_visits'), (string) $kpi['visits']);
        $html .= $this->renderKpiCard($this->addon->i18n('statistics_report_kpi_visitors'), (string) $kpi['visitors']);
        $html .= $this->renderKpiCard($this->addon->i18n('statistics_report_kpi_pages_per_session'), number_format((float) $kpi['pagesPerSession'], 2, ',', '.'));
        $html .= $this->renderKpiCard($this->addon->i18n('statistics_report_kpi_active_days'), (string) $kpi['activeDays']);
        $html .= '</tr></table>';

        $html .= '<p class="small"><strong>' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_top_page'), ENT_QUOTES) . ':</strong> '
            . htmlspecialchars($kpi['topPage'], ENT_QUOTES) . ' (' . (int) $kpi['topPageCount'] . ')</p>';
        $html .= '<p class="small"><strong>' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_top_referer'), ENT_QUOTES) . ':</strong> '
            . htmlspecialchars($kpi['topReferer'], ENT_QUOTES) . ' (' . (int) $kpi['topRefererCount'] . ')</p>';

        $html .= '<div class="graphics-grid">';
        $html .= '<div class="graphics-card">';
        $html .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_traffic_title'), ENT_QUOTES) . '</h3>';
        $html .= $this->renderTrafficCompareBars($kpi['visits'], $kpi['visitors']);
        $html .= '</div>';
        $html .= '<div class="graphics-card">';
        $html .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_daily_title'), ENT_QUOTES) . '</h3>';
        $html .= $this->renderDailyBars($dailyVisits);
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="graphics-grid">';
        $html .= '<div class="graphics-card graphics-card-full">';
        $html .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_device_types_title'), ENT_QUOTES) . '</h3>';
        $html .= $this->renderTopBarList($deviceTypes, '#8a5cf6');
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="graphics-grid">';
        $html .= '<div class="graphics-card">';
        $html .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_top_pages_title'), ENT_QUOTES) . '</h3>';
        $html .= $this->renderTopBarList($topPages, '#2f80c8');
        $html .= '</div>';
        $html .= '<div class="graphics-card">';
        $html .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_top_referers_title'), ENT_QUOTES) . '</h3>';
        $html .= $this->renderTopBarList($topReferers, '#20a39e');
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="section">';
        $html .= '<h2>' . htmlspecialchars($this->addon->i18n('statistics_report_top_pages_title'), ENT_QUOTES) . '</h2>';
        $html .= $this->renderTopTable($topPages);
        $html .= '</div>';

        $html .= '<div class="section">';
        $html .= '<h2>' . htmlspecialchars($this->addon->i18n('statistics_report_top_referers_title'), ENT_QUOTES) . '</h2>';
        $html .= $this->renderTopTable($topReferers);
        $html .= '</div>';

        $html .= '<p class="muted">' . htmlspecialchars($this->addon->i18n('statistics_report_footer_note'), ENT_QUOTES) . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    private function renderKpiCard(string $label, string $value): string
    {
        $html = '<td class="kpi">';
        $html .= '<div class="klabel">' . htmlspecialchars($label, ENT_QUOTES) . '</div>';
        $html .= '<div class="kvalue">' . htmlspecialchars($value, ENT_QUOTES) . '</div>';
        $html .= '</td>';

        return $html;
    }

    private function renderTrafficCompareBars(int $visits, int $visitors): string
    {
        $max = max($visits, $visitors, 1);
        $visitsWidth = (int) round(($visits / $max) * 100);
        $visitorsWidth = (int) round(($visitors / $max) * 100);

        $html = '<p class="legend">';
        $html .= '<span class="legend-item"><span class="legend-dot" style="background:#2f80c8"></span>' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_visits'), ENT_QUOTES) . '</span>';
        $html .= '<span class="legend-item"><span class="legend-dot" style="background:#20a39e"></span>' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_visitors'), ENT_QUOTES) . '</span>';
        $html .= '</p>';

        $html .= '<div class="compare-row">';
        $html .= '<div class="compare-label">' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_visits'), ENT_QUOTES) . ': ' . $visits . '</div>';
        $html .= '<div class="compare-track"><div class="compare-fill" style="width:' . $visitsWidth . '%"></div></div>';
        $html .= '</div>';

        $html .= '<div class="compare-row">';
        $html .= '<div class="compare-label">' . htmlspecialchars($this->addon->i18n('statistics_report_kpi_visitors'), ENT_QUOTES) . ': ' . $visitors . '</div>';
        $html .= '<div class="compare-track"><div class="compare-fill-alt" style="width:' . $visitorsWidth . '%"></div></div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array{date:string,count:int}> $dailyVisits
     */
    private function renderDailyBars(array $dailyVisits): string
    {
        if ([] === $dailyVisits) {
            return '<p class="small">' . htmlspecialchars($this->addon->i18n('statistics_no_data'), ENT_QUOTES) . '</p>';
        }

        $max = max(array_column($dailyVisits, 'count'));
        if ($max < 1) {
            $max = 1;
        }

        $bars = array_slice($dailyVisits, -10);
        $html = '<p class="legend"><span class="legend-item"><span class="legend-dot" style="background:#2f80c8"></span>' . htmlspecialchars($this->addon->i18n('statistics_report_graph_daily_series_label'), ENT_QUOTES) . '</span></p>';
        $html .= '<p class="chart-note">' . htmlspecialchars($this->addon->i18n('statistics_report_graph_max_label') . ': ' . (string) $max, ENT_QUOTES) . '</p>';
        $html .= '<table class="hbar-table">';
        foreach ($bars as $row) {
            $width = max(2, (int) round(($row['count'] / $max) * 100));
            $date = (string) ($row['date'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            $html .= '<tr>';
            $html .= '<td class="hbar-label">' . htmlspecialchars($date, ENT_QUOTES) . '</td>';
            $html .= '<td class="hbar-track-cell"><div class="hbar-track"><div class="hbar-fill" style="width:' . $width . '%"></div></div></td>';
            $html .= '<td class="hbar-count">' . $count . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<int, array{item:string,count:int}> $rows
     */
    private function renderTopBarList(array $rows, string $color): string
    {
        if ([] === $rows) {
            return '<p class="small">' . htmlspecialchars($this->addon->i18n('statistics_no_data'), ENT_QUOTES) . '</p>';
        }

        $list = array_slice($rows, 0, 8);
        $max = max(array_column($list, 'count'));
        if ($max < 1) {
            $max = 1;
        }

        $html = '<table class="hbar-table">';
        foreach ($list as $row) {
            $width = (int) round(($row['count'] / $max) * 100);
            $html .= '<tr>';
            $html .= '<td class="hbar-label">' . htmlspecialchars($row['item'], ENT_QUOTES) . '</td>';
            $html .= '<td class="hbar-track-cell"><div class="hbar-track"><div class="hbar-fill" style="width:' . $width . '%;background:' . htmlspecialchars($color, ENT_QUOTES) . ';"></div></div></td>';
            $html .= '<td class="hbar-count">' . $row['count'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<int, array{item:string,count:int}> $rows
     */
    private function renderTopTable(array $rows): string
    {
        if ([] === $rows) {
            return '<p class="small">' . htmlspecialchars($this->addon->i18n('statistics_no_data'), ENT_QUOTES) . '</p>';
        }

        $html = '<table class="list">';
        $html .= '<thead><tr>';
        $html .= '<th style="width:50px">#</th>';
        $html .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_name'), ENT_QUOTES) . '</th>';
        $html .= '<th style="width:90px">' . htmlspecialchars($this->addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $index => $row) {
            $html .= '<tr>';
            $html .= '<td class="num">' . ($index + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['item'], ENT_QUOTES) . '</td>';
            $html .= '<td class="num">' . $row['count'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
