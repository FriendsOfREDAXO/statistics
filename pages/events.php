<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\EventDetails;
use AndiLeni\Statistics\StatsChartConfig;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

$current_backend_page = rex_get('page', 'string', '');
$search_string = htmlspecialchars_decode(rex_request('search_string', 'string', ''));
$request_name = rex_request('name', 'string', '');
$request_name = htmlspecialchars_decode($request_name);
$delete_entry = rex_request('delete_entry', 'boolean', false);
$request_date_start = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$request_date_end = htmlspecialchars_decode(rex_request('date_end', 'string', ''));
$actionsToken = rex_csrf_token::factory('statistics_events_delete');

if ($request_name !== '' && $delete_entry && !$actionsToken->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
}

$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_api');
echo StatsSubpageRenderer::renderFilter($current_backend_page, $filter_date_helper);


if ($request_name !== '' && $delete_entry === true && $actionsToken->isValid()) {
    $sql = rex_sql::factory();
    $sql->setQuery('delete from ' . rex::getTable('pagestats_api') . ' where name = :name', ['name' => $request_name]);
    echo rex_view::success(sprintf($addon->i18n('statistics_deleted_campaign_entries'), (string) $sql->getRows(), rex_escape($request_name)));
}

// details section
if ($request_name !== '' && !$delete_entry) {
    // details section for single campaign

    $pagedetails = new EventDetails($request_name, $filter_date_helper);
    $sum_data = $pagedetails->getSumPerDay();


    $content = '<div id="chart_details" style="height:500px; width:auto"></div>';

    echo StatsSubpageRenderer::renderInfoSection(
        $addon->i18n('statistics_details_for'),
        $request_name,
        $content . StatsChartConfig::renderScript('chart_details', StatsChartConfig::buildTimelineOption($sum_data['labels'], $sum_data['values']))
    );
}
$sql = rex_sql::factory();
$eventRows = $sql->getArray(
    'SELECT name, SUM(count) AS count FROM ' . rex::getTable('pagestats_api')
    . ' WHERE date BETWEEN :start AND :end GROUP BY name ORDER BY count DESC',
    [
        'start' => $filter_date_helper->date_start->format('Y-m-d'),
        'end' => $filter_date_helper->date_end->format('Y-m-d'),
    ]
);

if ([] === $eventRows) {
    $table = rex_view::info($addon->i18n('statistics_no_data'));
} else {
    $table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table">';
    $table .= '<thead><tr>';
    $table .= '<th>' . rex_escape($addon->i18n('statistics_api_name')) . '</th>';
    $table .= '<th>' . rex_escape($addon->i18n('statistics_api_count')) . '</th>';
    $table .= '<th>' . rex_escape($addon->i18n('statistics_api_delete')) . '</th>';
    $table .= '</tr></thead><tbody>';

    foreach ($eventRows as $row) {
        $name = (string) $row['name'];
        $count = (string) $row['count'];
        $detailUrl = rex_context::fromGet()->getUrl([
            'name' => $name,
            'date_start' => $filter_date_helper->date_start->format('Y-m-d'),
            'date_end' => $filter_date_helper->date_end->format('Y-m-d'),
        ]);
        $deleteUrl = rex_context::fromGet()->getUrl([
            'name' => $name,
            'delete_entry' => true,
        ] + $actionsToken->getUrlParams());
        $confirm = rex_escape($name . PHP_EOL . $addon->i18n('statistics_api_delete_confirm'));

        $table .= '<tr>';
        $table .= '<td><a href="' . rex_escape($detailUrl) . '">' . rex_escape($name) . '</a></td>';
        $table .= '<td data-sort="' . rex_escape($count) . '">' . rex_escape($count) . '</td>';
        $table .= '<td><a href="' . rex_escape($deleteUrl) . '" data-confirm="' . $confirm . '">' . rex_escape($addon->i18n('statistics_api_delete')) . '</a></td>';
        $table .= '</tr>';
    }

    $table .= '</tbody></table>';
}

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_api_campaign_views'), $table);

?>
