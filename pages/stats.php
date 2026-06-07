<?php

use AndiLeni\Statistics\chartData;
use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\StatsDashboard;
use AndiLeni\Statistics\Summary;

$addon = rex_addon::get('statistics');

// BASIC INITIALISATION 

$current_backend_page = rex_get('page', 'string', '');
$request_date_start = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$request_date_end = htmlspecialchars_decode(rex_request('date_end', 'string', ''));

$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_visits_per_day');



// data for charts
$chart_data = new chartData($filter_date_helper);


// main chart data for visits and visitors
$main_chart_data = $chart_data->getMainChartData();

// heatmap data for visits per day in this year
$data_heatmap = $chart_data->getHeatmapVisits();

// overview of visits and visitors of today, total and filered by date
$overview = new Summary($filter_date_helper);
$overview_data = $overview->getSummaryData();

echo StatsDashboard::renderFilter($current_backend_page, $filter_date_helper);
echo StatsDashboard::renderOverview($filter_date_helper, $overview_data);
echo StatsDashboard::renderMainChartSection($filter_date_helper);
echo StatsDashboard::renderAnalysisCards($filter_date_helper);
echo StatsDashboard::renderLazyPlaceholders($filter_date_helper);
echo StatsDashboard::renderPageConfigScript(StatsDashboard::buildPageConfig($main_chart_data, $data_heatmap));

?>
