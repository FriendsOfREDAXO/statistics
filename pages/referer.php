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

if ([] === $refererRows) {
    $table = rex_view::info($addon->i18n('statistics_no_data'));
} else {
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
