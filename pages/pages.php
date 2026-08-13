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
$toggle_favorite = rex_request('toggle_favorite', 'boolean', false);
$only_favorites = rex_request('only_favorites', 'boolean', false);
$actionsToken = rex_csrf_token::factory('statistics_pages_actions');


$filter_date_helper = new DateFilter($request_date_start, $request_date_end, 'pagestats_visits_per_url');
$pages_helper = new Pages($filter_date_helper);

if (($ignore_page || $toggle_favorite) && !$actionsToken->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
}

echo StatsSubpageRenderer::renderFilter($current_backend_page, $filter_date_helper);

// sum per page, bar chart
$chartLimit = 30;
$sum_per_page = $pages_helper->sumPerPage($httpstatus, $chartLimit);
$tableLimit = 500;
$chartBody = '';

if ([] === $sum_per_page) {
    $chartBody .= rex_view::info($addon->i18n('statistics_no_data'));
} else {
    $chartBody .= '<div class="alert alert-info" style="margin-bottom:10px;">';
    $chartBody .= sprintf(
        rex_escape($addon->i18n('statistics_top_pages_period')),
        rex_escape((string) $chartLimit)
    );
    $chartBody .= '</div>';
    $chartBody .= '<div id="chart_visits_per_page" style="height:640px; width:100%"></div>';
    $chartBody .= StatsChartConfig::renderScript('chart_visits_per_page', StatsChartConfig::buildPagesStackedBarOption($sum_per_page, $chartLimit));
}


// check if request is for ignoring a url
// if yes, add url to addon settings and delete all database entries of this url 
if ($request_url !== '' && $ignore_page === true && $actionsToken->isValid()) {
    $rows = $pages_helper->ignorePage($request_url);
    echo rex_view::success(
        sprintf($addon->i18n('statistics_ignore_success'), (string) $rows)
        . ' '
        . sprintf($addon->i18n('statistics_ignore_url_future'), rex_escape($request_url))
    );
}

if ($request_url !== '' && $toggle_favorite === true && $actionsToken->isValid()) {
    $isFavoriteNow = $pages_helper->toggleFavoriteUrl($request_url);
    $messageKey = $isFavoriteNow ? 'statistics_favorite_toggle_add' : 'statistics_favorite_toggle_remove';
    echo rex_view::success($addon->i18n($messageKey) . ': <code>' . rex_escape($request_url) . '</code>');
}


// details for one url requested
if ($request_url !== '' && !$ignore_page) {
    // details section for single page

    $pagedetails = new PageDetails($request_url, $filter_date_helper);
    $sum_data = $pagedetails->getSumPerDay();

    $escaped_url = rex_escape($request_url);
    $content = '<h4>' . $addon->i18n('statistics_views_total') . ' <b>' . $pagedetails->getPageTotal() . '</b></h4><a href="http://' . $escaped_url . '" target="_blank">' . $escaped_url . '</a>';
    $content .= '<div id="chart_details" style="height:500px; width:auto"></div>';
    $content .= StatsChartConfig::renderScript('chart_details', StatsChartConfig::buildTimelineOption($sum_data['labels'], $sum_data['values']));
    $content .= $pagedetails->getList();

    echo StatsSubpageRenderer::renderInfoSection($addon->i18n('statistics_details_for'), $request_url, $content);
}


// list of all pages
$sql = rex_sql::factory();
$domains = $sql->getArray('SELECT distinct domain FROM ' . rex::getTable('pagestats_visits_per_day'));
$domain_select = '<div class="statistics-pages-domain-filter">';
$domain_select .= '<label for="stats_domain_select">' . rex_escape($addon->i18n('statistics_domain_filter_label')) . '</label>';
$domain_select .= '<select id="stats_domain_select" class="form-control">';
$domain_select .= '<option value="">' . rex_escape($addon->i18n('statistics_all_domains')) . '</option>';
foreach ($domains as $domain) {
    $escaped_domain = rex_escape((string) $domain['domain']);
    $domain_select .= '<option value="' . $escaped_domain . '">' . $escaped_domain . '</option>';
}
$domain_select .= '</select>';
$domain_select .= '<small>' . rex_escape($addon->i18n('statistics_domain_filter_note')) . '</small>';
$domain_select .= '</div>';


// buttons to filter by http status
$baseParams = [
    'page' => 'statistics/pages',
    'date_start' => $filter_date_helper->date_start->format('Y-m-d'),
    'date_end' => $filter_date_helper->date_end->format('Y-m-d'),
    'url' => '',
    'only_favorites' => $only_favorites ? 1 : 0,
];
$oa = rex_url::backendController(array_merge($baseParams, ['httpstatus' => 'any']), false);
$o2 = rex_url::backendController(array_merge($baseParams, ['httpstatus' => '200']), false);
$on2 = rex_url::backendController(array_merge($baseParams, ['httpstatus' => 'not200']), false);
$of = rex_url::backendController(array_merge($baseParams, ['httpstatus' => $httpstatus, 'only_favorites' => 1]), false);
$oaf = rex_url::backendController(array_merge($baseParams, ['httpstatus' => $httpstatus, 'only_favorites' => 0]), false);

$http_filter_buttons = '<div class="statistics-pages-filter-bar">';
$http_filter_buttons .= '<div class="btn-group" role="group" aria-label="' . rex_escape($addon->i18n('statistics_http_status_filter_aria')) . '">';
$http_filter_buttons .= '<a class="btn btn-primary" href="' . $oa . '">' . rex_escape($addon->i18n('statistics_filter_all')) . '</a>
<a class="btn btn-primary" href="' . $o2 . '">' . rex_escape($addon->i18n('statistics_filter_only_200')) . '</a>
<a class="btn btn-primary" href="' . $on2 . '">' . rex_escape($addon->i18n('statistics_filter_only_not_200')) . '</a>
<a class="btn btn-primary" href="' . $of . '">' . rex_escape($addon->i18n('statistics_filter_only_favorites')) . '</a>
<a class="btn btn-default" href="' . $oaf . '">' . rex_escape($addon->i18n('statistics_filter_all')) . '</a>';
$http_filter_buttons .= '</div>';
$http_filter_buttons .= $domain_select;
$http_filter_buttons .= '</div>';


echo StatsSubpageRenderer::renderSection(
    $addon->i18n('statistics_sum_per_page'),
    $http_filter_buttons . $chartBody . $pages_helper->getList($httpstatus, $tableLimit, $only_favorites)
);

?>
