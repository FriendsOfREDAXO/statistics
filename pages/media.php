<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\MediaDetails;
use AndiLeni\Statistics\StatsChartConfig;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

$current_backend_page = rex_get('page', 'string', '');
$search_string = htmlspecialchars_decode(rex_request('search_string', 'string', ''));
$request_url = rex_request('url', 'string', '');
$request_url = htmlspecialchars_decode($request_url);
$delete_entry = rex_request('delete_entry', 'boolean', false);
$request_date_start = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$request_date_end = htmlspecialchars_decode(rex_request('date_end', 'string', ''));

$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_media');
echo StatsSubpageRenderer::renderFilter($current_backend_page, $filter_date_helper);

if ($request_url !== '' && $delete_entry === true) {
    $sql = rex_sql::factory();
    $sql->setQuery('delete from ' . rex::getTable('pagestats_media') . ' where url = :url', ['url' => $request_url]);
    echo rex_view::success(sprintf($addon->i18n('statistics_deleted_campaign_entries'), (string) $sql->getRows(), htmlspecialchars($request_url, ENT_QUOTES)));
}

// details section
if ($request_url !== '' && !$delete_entry) {
    // details section for single campaign

    $pagedetails = new MediaDetails($request_url, $filter_date_helper);
    $sum_data = $pagedetails->getSumPerDay();

    $content = '<div id="chart_details" style="height:500px; width:auto"></div>';

    echo StatsSubpageRenderer::renderInfoSection(
        $addon->i18n('statistics_details_for'),
        $request_url,
        $content . StatsChartConfig::renderScript('chart_details', StatsChartConfig::buildTimelineOption($sum_data['labels'], $sum_data['values']))
    );
}
$sql = rex_sql::factory();
$mediaRows = $sql->getArray(
    'SELECT url, SUM(count) AS count FROM ' . rex::getTable('pagestats_media')
    . ' WHERE date BETWEEN :start AND :end GROUP BY url ORDER BY count DESC',
    [
        'start' => $filter_date_helper->date_start->format('Y-m-d'),
        'end' => $filter_date_helper->date_end->format('Y-m-d'),
    ]
);

if ([] === $mediaRows) {
    $table = rex_view::info($addon->i18n('statistics_no_data'));
} else {
    $table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table">';
    $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_media_url'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_media_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';

    foreach ($mediaRows as $row) {
        $url = (string) $row['url'];
        $count = (string) $row['count'];
        $detailUrl = rex_context::fromGet()->getUrl([
            'url' => $url,
            'date_start' => $filter_date_helper->date_start->format('Y-m-d'),
            'date_end' => $filter_date_helper->date_end->format('Y-m-d'),
        ]);

        $table .= '<tr>';
        $table .= '<td><a href="' . htmlspecialchars($detailUrl, ENT_QUOTES) . '">' . htmlspecialchars($url, ENT_QUOTES) . '</a></td>';
        $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
        $table .= '</tr>';
    }

    $table .= '</tbody></table>';
}

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_media_views'), $table);

?>
