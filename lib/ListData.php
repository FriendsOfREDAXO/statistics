<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_addon_interface;
use rex_fragment;
use rex_sql;
use rex_sql_exception;
use rex_view;
use InvalidArgumentException;

class ListData
{
    private DateFilter $filter_date_helper;
    private rex_addon_interface $addon;


    /**
     * 
     * 
     * @param DateFilter $filter_date_helper 
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct(DateFilter $filter_date_helper)
    {
        $this->filter_date_helper = $filter_date_helper;
        $this->addon = rex_addon::get('statistics');
    }


    /**
     * 
     * 
     * @return rex_fragment 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getListsDaily(): rex_fragment
    {
        $fragment_collapse = new rex_fragment();
        $fragment_collapse->setVar('title', $this->addon->i18n('statistics_views_per_day'));
        $fragment_collapse->setVar('content', $this->getDailyContent(), false);

        return $fragment_collapse;
    }


    /**
     * 
     * 
     * @return rex_fragment 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getListsMonthly(): rex_fragment
    {
        $fragment_collapse = new rex_fragment();
        $fragment_collapse->setVar('title', $this->addon->i18n('statistics_views_per_day'));
        $fragment_collapse->setVar('content', $this->getMonthlyContent(), false);

        return $fragment_collapse;
    }


    /**
     * 
     * 
     * @return rex_fragment 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getListsYearly(): rex_fragment
    {
        $fragment_collapse = new rex_fragment();
        $fragment_collapse->setVar('title', $this->addon->i18n('statistics_views_per_day'));
        $fragment_collapse->setVar('content', $this->getYearlyContent(), false);

        return $fragment_collapse;
    }

    public function getDailyContent(): string
    {
        $table = '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visits_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getDailyRows('pagestats_visits_per_day'),
            'date',
            $this->addon->i18n('statistics_date')
        );

        $table .= '<hr>';

        $table .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visitors_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getDailyRows('pagestats_visitors_per_day'),
            'date',
            $this->addon->i18n('statistics_date')
        );

        return $table;
    }

    public function getMonthlyContent(): string
    {
        $table = '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visits_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getMonthlyRows('pagestats_visits_per_day'),
            'month',
            $this->addon->i18n('statistics_month')
        );

        $table .= '<hr>';

        $table .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visitors_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getMonthlyRows('pagestats_visitors_per_day'),
            'month',
            $this->addon->i18n('statistics_month')
        );

        return $table;
    }

    public function getYearlyContent(): string
    {
        $table = '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visits_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getYearlyRows('pagestats_visits_per_day'),
            'year',
            $this->addon->i18n('statistics_year')
        );

        $table .= '<hr>';

        $table .= '<h3>' . htmlspecialchars($this->addon->i18n('statistics_list_visitors_heading'), ENT_QUOTES) . '</h3>' . $this->renderTimeTable(
            $this->getYearlyRows('pagestats_visitors_per_day'),
            'year',
            $this->addon->i18n('statistics_year')
        );

        return $table;
    }

    /**
     * @return array<int, array{date: string, count: int}>
     * @throws rex_sql_exception
     */
    private function getDailyRows(string $table): array
    {
        if ('' === trim($table)) {
            return [];
        }

        $sql = rex_sql::factory();

        return array_map(
            static fn(array $row): array => [
                'date' => (string) $row['date'],
                'count' => (int) $row['count'],
            ],
            $sql->getArray(
                'SELECT date, SUM(count) AS count FROM ' . rex::getTable($table)
                . ' WHERE date BETWEEN :start AND :end GROUP BY date ORDER BY count DESC',
                [
                    'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                ]
            )
        );
    }

    /**
     * @return array<int, array{month: string, count: int, sort_value: string}>
     * @throws rex_sql_exception
     */
    private function getMonthlyRows(string $table): array
    {
        if ('' === trim($table)) {
            return [];
        }

        $sql = rex_sql::factory();

        return array_map(
            static fn(array $row): array => [
                'month' => (string) $row['month'],
                'count' => (int) $row['count'],
                'sort_value' => (string) $row['sort_value'],
            ],
            $sql->getArray(
                'SELECT DATE_FORMAT(date, "%m.%Y") AS month, SUM(count) AS count, DATE_FORMAT(date, "%Y-%m") AS sort_value '
                . 'FROM ' . rex::getTable($table)
                . ' GROUP BY YEAR(date), MONTH(date) ORDER BY YEAR(date) DESC, MONTH(date) DESC'
            )
        );
    }

    /**
     * @return array<int, array{year: string, count: int}>
     * @throws rex_sql_exception
     */
    private function getYearlyRows(string $table): array
    {
        if ('' === trim($table)) {
            return [];
        }

        $sql = rex_sql::factory();

        return array_map(
            static fn(array $row): array => [
                'year' => (string) $row['year'],
                'count' => (int) $row['count'],
            ],
            $sql->getArray(
                'SELECT DATE_FORMAT(date, "%Y") AS year, SUM(count) AS count '
                . 'FROM ' . rex::getTable($table)
                . ' GROUP BY YEAR(date) ORDER BY YEAR(date) DESC'
            )
        );
    }

    /**
     * @param array<int, array<string, int|string>> $rows
     */
    private function renderTimeTable(array $rows, string $labelKey, string $labelTitle): string
    {
        if ([] === $rows) {
            return rex_view::info($this->addon->i18n('statistics_no_data'));
        }

        $table = '<table class="table-bordered dt_order_first statistics_table table-striped table-hover table">';
        $table .= '<thead><tr>';
        $table .= '<th>' . htmlspecialchars($labelTitle, ENT_QUOTES) . '</th>';
        $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
        $table .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $label = (string) $row[$labelKey];
            $count = (string) $row['count'];
            $sortValue = isset($row['sort_value']) ? (string) $row['sort_value'] : $label;

            if ('date' === $labelKey) {
                $timestamp = strtotime($label);
                if (false !== $timestamp) {
                    $label = date('d.m.Y', $timestamp);
                }
            }

            $table .= '<tr>';
            $table .= '<td data-sort="' . htmlspecialchars($sortValue, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</td>';
            $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table>';

        return $table;
    }
}
