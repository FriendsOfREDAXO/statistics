<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\RefererDetails;
use AndiLeni\Statistics\StatsChartConfig;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

$current_backend_page = rex_get('page', 'string', '');
$request_date_start = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$request_date_end = htmlspecialchars_decode(rex_request('date_end', 'string', ''));
$request_ref = htmlspecialchars_decode(rex_request('referer', 'string', ''));

$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_referer');
echo StatsSubpageRenderer::renderFilter($current_backend_page, $filter_date_helper);

// details for one url requested
if ($request_ref !== '') {
    // details section for single page

    $refererDetails = new RefererDetails($request_ref, $filter_date_helper);
    $sum_data = $refererDetails->getSumPerDay();

    echo StatsSubpageRenderer::renderInfoSection(
        $addon->i18n('statistics_details_for'),
        $request_ref,
        '<a target="_blank" href="' . htmlspecialchars($request_ref, ENT_QUOTES) . '">' . htmlspecialchars($request_ref, ENT_QUOTES) . '</a><div id="chart_details" style="height:500px; width:auto"></div>' . StatsChartConfig::renderScript('chart_details', StatsChartConfig::buildTimelineOption($sum_data['labels'], $sum_data['values'])) . $refererDetails->getList()
    );
}

$sql = rex_sql::factory();
$refererRows = $sql->getArray(
    'SELECT referer, SUM(count) AS count FROM ' . rex::getTable('pagestats_referer')
    . ' WHERE date BETWEEN :start AND :end GROUP BY referer ORDER BY count DESC, referer ASC',
    [
        'start' => $filter_date_helper->date_start->format('Y-m-d'),
        'end' => $filter_date_helper->date_end->format('Y-m-d'),
    ]
);

$extractHost = static function (string $referer): string {
    $trimmed = trim($referer);
    if ('' === $trimmed) {
        return '-';
    }

    $host = parse_url($trimmed, PHP_URL_HOST);
    if (is_string($host) && '' !== $host) {
        return strtolower($host);
    }

    // Fallback for schemeless values like "example.org/path".
    $host = parse_url('http://' . ltrim($trimmed, '/'), PHP_URL_HOST);
    if (is_string($host) && '' !== $host) {
        return strtolower($host);
    }

    return '-';
};

$totalRefererCalls = 0;
$hostCounts = [];

foreach ($refererRows as $row) {
    $count = (int) $row['count'];
    $totalRefererCalls += $count;

    $host = $extractHost((string) $row['referer']);
    if (!isset($hostCounts[$host])) {
        $hostCounts[$host] = 0;
    }
    $hostCounts[$host] += $count;
}

arsort($hostCounts);
$topHosts = array_slice($hostCounts, 0, 6, true);

if ([] === $refererRows) {
    $table = rex_view::info($addon->i18n('statistics_no_data'));
} else {
    $topReferers = array_slice($refererRows, 0, 6);
    $topReferersMax = max(array_map(static fn (array $row): int => (int) $row['count'], $topReferers));

    $kpiBody = '<div class="row">';
    $kpiBody .= '<div class="col-sm-4"><div class="panel panel-default statistics-kpi-panel"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_referer_kpi_total_calls'), ENT_QUOTES) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) $totalRefererCalls, ENT_QUOTES) . '</div></div></div></div>';
    $kpiBody .= '<div class="col-sm-4"><div class="panel panel-default statistics-kpi-panel"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_referer_kpi_unique_referers'), ENT_QUOTES) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) count($refererRows), ENT_QUOTES) . '</div></div></div></div>';
    $kpiBody .= '<div class="col-sm-4"><div class="panel panel-default statistics-kpi-panel"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_referer_kpi_unique_hosts'), ENT_QUOTES) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) count($hostCounts), ENT_QUOTES) . '</div></div></div></div>';
    $kpiBody .= '</div>';

    $hostBody = '<div class="row">';
    if ([] === $topHosts) {
        $hostBody .= '<div class="col-sm-12">' . rex_view::info($addon->i18n('statistics_no_data')) . '</div>';
    } else {
        $topHostMax = max($topHosts);
        foreach ($topHosts as $host => $hostCount) {
            $hostShare = $topHostMax > 0 ? (int) round(($hostCount / $topHostMax) * 100) : 0;
            $hostBody .= '<div class="col-sm-6 col-md-4">';
            $hostBody .= '<div class="statistics-ref-host-card">';
            $hostBody .= '<div class="statistics-ref-host-title" title="' . htmlspecialchars((string) $host, ENT_QUOTES) . '">' . htmlspecialchars((string) $host, ENT_QUOTES) . '</div>';
            $hostBody .= '<div class="statistics-ref-host-count">' . htmlspecialchars((string) $hostCount, ENT_QUOTES) . '</div>';
            $hostBody .= '<div class="statistics-ref-meter"><div class="statistics-ref-meter-bar" style="width:' . $hostShare . '%"></div></div>';
            $hostBody .= '</div>';
            $hostBody .= '</div>';
        }
    }
    $hostBody .= '</div>';

    $topBody = '<div class="row">';
    foreach ($topReferers as $row) {
        $referer = (string) $row['referer'];
        $count = (int) $row['count'];
        $detailUrl = rex_context::fromGet()->getUrl([
            'referer' => $referer,
            'date_start' => $filter_date_helper->date_start->format('Y-m-d'),
            'date_end' => $filter_date_helper->date_end->format('Y-m-d'),
        ]);
        $topShare = $topReferersMax > 0 ? (int) round(($count / $topReferersMax) * 100) : 0;

        $topBody .= '<div class="col-sm-6">';
        $topBody .= '<a class="statistics-ref-top-card" href="' . htmlspecialchars($detailUrl, ENT_QUOTES) . '">';
        $topBody .= '<div class="statistics-ref-top-url" title="' . htmlspecialchars($referer, ENT_QUOTES) . '">' . htmlspecialchars($referer, ENT_QUOTES) . '</div>';
        $topBody .= '<div class="statistics-ref-top-count">' . htmlspecialchars((string) $count, ENT_QUOTES) . '</div>';
        $topBody .= '<div class="statistics-ref-meter"><div class="statistics-ref-meter-bar" style="width:' . $topShare . '%"></div></div>';
        $topBody .= '</a>';
        $topBody .= '</div>';
    }
    $topBody .= '</div>';

    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_referer_overview_heading'), $kpiBody);
    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_referer_top_hosts_heading'), $hostBody);
    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_referer_top_referers_heading'), $topBody);

    $table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table">';
    $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_referer'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';

    foreach ($refererRows as $row) {
        $referer = (string) $row['referer'];
        $count = (string) $row['count'];
        $detailUrl = rex_context::fromGet()->getUrl([
            'referer' => $referer,
            'date_start' => $filter_date_helper->date_start->format('Y-m-d'),
            'date_end' => $filter_date_helper->date_end->format('Y-m-d'),
        ]);

        $table .= '<tr>';
        $table .= '<td><a href="' . htmlspecialchars($detailUrl, ENT_QUOTES) . '">' . htmlspecialchars($referer, ENT_QUOTES) . '</a></td>';
        $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
        $table .= '</tr>';
    }

    $table .= '</tbody></table>';
}

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_all_referer'), $table);

?>
