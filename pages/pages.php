<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\Pages;
use AndiLeni\Statistics\PageDetails;
use AndiLeni\Statistics\StatsChartConfig;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

$current_backend_page = rex_get('page', 'string', '');
$request_url = rex_request('url', 'string', '');
$request_url = htmlspecialchars_decode($request_url);
$ignore_page = rex_request('ignore_page', 'boolean', false);
$search_string = htmlspecialchars_decode(rex_request('search_string', 'string', ''));
$request_date_start = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$request_date_end = htmlspecialchars_decode(rex_request('date_end', 'string', ''));
$httpstatus = rex_request('httpstatus', 'string', 'any');


$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_visits_per_url');
$pages_helper = new Pages($filter_date_helper);

echo StatsSubpageRenderer::renderFilter($current_backend_page, $filter_date_helper);

// sum per page, bar chart
$sum_per_page = $pages_helper->sumPerPage($httpstatus);
$chartLimit = 30;
$chartBody = '';

if ([] === $sum_per_page) {
    $chartBody .= rex_view::info($addon->i18n('statistics_no_data'));
} else {
    $chartBody .= '<div class="alert alert-info" style="margin-bottom:10px;">';
    $chartBody .= 'Top ' . htmlspecialchars((string) $chartLimit, ENT_QUOTES) . ' Seiten nach Aufrufen im gewählten Zeitraum.';
    $chartBody .= '</div>';
    $chartBody .= '<div id="chart_visits_per_page" style="height:640px; width:100%"></div>';
    $chartBody .= StatsChartConfig::renderScript('chart_visits_per_page', StatsChartConfig::buildPagesStackedBarOption($sum_per_page, $chartLimit));
}


// check if request is for ignoring a url
// if yes, add url to addon settings and delete all database entries of this url 
if ($request_url !== '' && $ignore_page === true) {
    $rows = $pages_helper->ignorePage($request_url);
    echo rex_view::success(
        sprintf($addon->i18n('statistics_ignore_success'), (string) $rows)
        . ' '
        . sprintf($addon->i18n('statistics_ignore_url_future'), htmlspecialchars($request_url, ENT_QUOTES))
    );
}


// details for one url requested
if ($request_url !== '' && !$ignore_page) {
    // details section for single page

    $pagedetails = new PageDetails($request_url, $filter_date_helper);
    $sum_data = $pagedetails->getSumPerDay();

    $content = '<h4>' . $addon->i18n('statistics_views_total') . ' <b>' . $pagedetails->getPageTotal() . '</b></h4><a href="http://' . $request_url . '" target="_blank">' . $request_url . '</a>';
    $content .= '<div id="chart_details" style="height:500px; width:auto"></div>';
    $content .= StatsChartConfig::renderScript('chart_details', StatsChartConfig::buildTimelineOption($sum_data['labels'], $sum_data['values']));
    $content .= $pagedetails->getList();

    echo StatsSubpageRenderer::renderInfoSection($addon->i18n('statistics_details_for'), $request_url, $content);
}


// list of all pages
$sql = rex_sql::factory();
$domains = $sql->getArray('SELECT distinct domain FROM ' . rex::getTable('pagestats_visits_per_day'));
$domain_select = '
<select id="stats_domain_select" class="form-control">
<option value="">' . htmlspecialchars($addon->i18n('statistics_all_domains'), ENT_QUOTES) . '</option>
';
foreach ($domains as $domain) {
    $domain_select .= '<option value="' . $domain['domain'] . '">' . $domain['domain'] . '</option>';
}
$domain_select .= '</select>';


// buttons to filter by http status
$oa = rex_context::fromGet()->getUrl(["httpstatus" => "any"]);
$o2 = rex_context::fromGet()->getUrl(["httpstatus" => "200"]);
$on2 = rex_context::fromGet()->getUrl(["httpstatus" => "not200"]);

$http_filter_buttons = '<a class="btn btn-primary" href="' . $oa . '">' . htmlspecialchars($addon->i18n('statistics_filter_all'), ENT_QUOTES) . '</a>
<a class="btn btn-primary" href="' . $o2 . '">' . htmlspecialchars($addon->i18n('statistics_filter_only_200'), ENT_QUOTES) . '</a>
<a class="btn btn-primary" href="' . $on2 . '">' . htmlspecialchars($addon->i18n('statistics_filter_only_not_200'), ENT_QUOTES) . '</a>';


echo StatsSubpageRenderer::renderSection(
    $addon->i18n('statistics_sum_per_page'),
    $http_filter_buttons . $chartBody . $domain_select . $pages_helper->getList($httpstatus)
);

?>