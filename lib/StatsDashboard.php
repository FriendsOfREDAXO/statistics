<?php

namespace AndiLeni\Statistics;

use rex;
use rex_config;
use rex_fragment;
use rex_i18n;

class StatsDashboard
{
    public static function renderFilter(string $currentBackendPage, DateFilter $filterDateHelper): string
    {
        $filterFragment = new rex_fragment();
        $filterFragment->setVar('current_backend_page', $currentBackendPage);
        $filterFragment->setVar('date_start', $filterDateHelper->date_start);
        $filterFragment->setVar('date_end', $filterDateHelper->date_end);
        $filterFragment->setVar('wts', $filterDateHelper->whole_time_start->format('Y-m-d'));

        return $filterFragment->parse('filter.php');
    }

    /**
     * @param array<string, mixed> $overviewData
     */
    public static function renderOverview(DateFilter $filterDateHelper, array $overviewData): string
    {
        $fragmentOverview = new rex_fragment();
        $fragmentOverview->setVar('date_start', $filterDateHelper->date_start);
        $fragmentOverview->setVar('date_end', $filterDateHelper->date_end);
        $fragmentOverview->setVar('filtered_visits', $overviewData['visits_datefilter']);
        $fragmentOverview->setVar('filtered_visitors', $overviewData['visitors_datefilter']);
        $fragmentOverview->setVar('today_visits', $overviewData['visits_today']);
        $fragmentOverview->setVar('today_visitors', $overviewData['visitors_today']);
        $fragmentOverview->setVar('total_visits', $overviewData['visits_total']);
        $fragmentOverview->setVar('total_visitors', $overviewData['visitors_total']);
        $fragmentOverview->setVar('visits_week', $overviewData['visits_week']);
        $fragmentOverview->setVar('visitors_week', $overviewData['visitors_week']);
        $fragmentOverview->setVar('top_article_path_week', $overviewData['top_article_path_week']);
        $fragmentOverview->setVar('top_article_count_week', $overviewData['top_article_count_week']);
        $fragmentOverview->setVar('pages_per_session_week', $overviewData['pages_per_session_week']);

        return $fragmentOverview->parse('overview.php');
    }

    public static function renderAnalysisCards(DateFilter $filterDateHelper): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('date_start', $filterDateHelper->date_start->format('Y-m-d'));
        $fragment->setVar('date_end', $filterDateHelper->date_end->format('Y-m-d'));

        return $fragment->parse('analysis_cards.php');
    }

    public static function renderMainChartSection(DateFilter $filterDateHelper): string
    {
        return StatsMainChartSection::render($filterDateHelper);
    }

    public static function renderLazyPlaceholders(DateFilter $filterDateHelper): string
    {
        return StatsLazySection::render($filterDateHelper);
    }

    /**
     * @param array<string, mixed> $mainChartData
     * @param array<string, mixed> $heatmapData
     * @return array<string, mixed>
     */
    public static function buildPageConfig(array $mainChartData, array $heatmapData): array
    {
        return [
            'showToolbox' => (bool) rex_config::get('statistics', 'statistics_show_chart_toolbox'),
            'mainChartData' => $mainChartData,
            'heatmap' => [
                'data' => $heatmapData['data'],
                'max' => $heatmapData['max'],
                'year' => (int) date('Y'),
            ],
            'tableLanguage' => self::getTableLanguage(),
        ];
    }

    /**
     * @param array<string, mixed> $pageConfig
     */
    public static function renderPageConfigScript(array $pageConfig): string
    {
        return '<script type="application/json" id="statistics-page-config">' . json_encode($pageConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public static function renderTableLanguageConfigScript(): string
    {
        return self::renderPageConfigScript([
            'tableLanguage' => self::getTableLanguage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getTableLanguage(): array
    {
        $language = (string) rex::getProperty('lang');
        $isGerman = 'de_de' === $language;

        return [
            'search' => '_INPUT_',
            'searchPlaceholder' => rex_i18n::msg('statistics_datatable_search_placeholder'),
            'decimal' => $isGerman ? ',' : '.',
            'info' => rex_i18n::msg('statistics_datatable_info'),
            'emptyTable' => rex_i18n::msg('statistics_datatable_empty_table'),
            'infoEmpty' => rex_i18n::msg('statistics_datatable_info_empty'),
            'infoFiltered' => rex_i18n::msg('statistics_datatable_info_filtered'),
            'lengthMenu' => rex_i18n::msg('statistics_datatable_length_menu'),
            'loadingRecords' => rex_i18n::msg('statistics_datatable_loading_records'),
            'zeroRecords' => rex_i18n::msg('statistics_datatable_zero_records'),
            'thousands' => $isGerman ? '.' : ',',
            'paginate' => [
                'first' => '<<',
                'last' => '>>',
                'next' => '>',
                'previous' => '<',
            ],
        ];
    }
}