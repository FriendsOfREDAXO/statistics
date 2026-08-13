<?php

namespace AndiLeni\Statistics;

use rex_addon;
use rex_fragment;

class StatsMainChartSection
{
    public static function render(DateFilter $filterDateHelper): string
    {
        $fragmentMainChart = new rex_fragment();

        $fragmentMainChart->setVar(
            'daily',
            '<div id="chart_visits_daily" style="width: 100%;height:500px;"></div><hr><div id="chart_visits_heatmap" style="width: 100%;height:200px;"></div>' . self::renderCollapse('main-daily-tables', $filterDateHelper),
            false
        );
        $fragmentMainChart->setVar(
            'monthly',
            '<div id="chart_visits_monthly" data-statistics-lazy-chart data-block-id="main-monthly-chart" data-date-start="' . rex_escape($filterDateHelper->date_start->format('Y-m-d')) . '" data-date-end="' . rex_escape($filterDateHelper->date_end->format('Y-m-d')) . '" data-state="idle" style="width: 100%;height:500px;"></div>' . self::renderCollapse('main-monthly-tables', $filterDateHelper),
            false
        );
        $fragmentMainChart->setVar(
            'yearly',
            '<div id="chart_visits_yearly" data-statistics-lazy-chart data-block-id="main-yearly-chart" data-date-start="' . rex_escape($filterDateHelper->date_start->format('Y-m-d')) . '" data-date-end="' . rex_escape($filterDateHelper->date_end->format('Y-m-d')) . '" data-state="idle" style="width: 100%;height:500px;"></div>' . self::renderCollapse('main-yearly-tables', $filterDateHelper),
            false
        );

        return $fragmentMainChart->parse('main_chart.php');
    }

    private static function renderCollapse(string $lazyBlockId, DateFilter $filterDateHelper): string
    {
        $collapse = new rex_fragment();
        $collapse->setVar('title', rex_addon::get('statistics')->i18n('statistics_views_per_day'));
        $collapse->setVar('lazy_block_id', $lazyBlockId);
        $collapse->setVar('date_start', $filterDateHelper->date_start->format('Y-m-d'));
        $collapse->setVar('date_end', $filterDateHelper->date_end->format('Y-m-d'));

        return $collapse->parse('collapse.php');
    }
}
