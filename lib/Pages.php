<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Helper class for the backend page "pages"
 * 
 */
class Pages
{

    private rex_addon $addon;
    private DateFilter $filter_date_helper;


    /**
     * 
     * 
     * @param DateFilter $filter_date_helper 
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct(DateFilter $filter_date_helper)
    {
        $this->addon = rex_addon::get('statistics');
        $this->filter_date_helper = $filter_date_helper;
    }


    /**
     * 
     * 
     * @return array 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function sumPerPage(string $httpstatus): array
    {
        return $this->getPageRows($httpstatus);
    }



    /**
     * 
     * 
     * @param string $request_url 
     * @return int 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function ignorePage(string $request_url): int
    {
        $ignored_paths = $this->addon->getConfig('statistics_ignored_paths');
        if ($ignored_paths == "") {
            $this->addon->setConfig('statistics_ignored_paths', $request_url);
        } else {
            $this->addon->setConfig('statistics_ignored_paths', $ignored_paths . PHP_EOL . $request_url);
        }

        $sql = rex_sql::factory();

        // get sum per day for substraction
        $sum_per_day = $sql->getArray('select date, sum(count) as "count" from ' . rex::getTable('pagestats_visits_per_url') . ' where url = :url group by date', ['url' => $request_url]);

        // reduce visits per day by these factors
        foreach ($sum_per_day as $e) {
            $sql->setQuery('update ' . rex::getTable('pagestats_visits_per_day') . ' set count = count - :v where date = :date', ['v' => $e['count'], 'date' => $e['date']]);
        }

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visits_per_url') . ' where url = :url', ['url' => $request_url]);

        return $sql->getRows() ?? 0;
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getList(string $httpstatus): string
    {
        $rows = $this->getPageRows($httpstatus);

        if ([] === $rows) {
            $table = rex_view::info($this->addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table" data-page-length="30">';
            $table .= '<thead><tr>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_url'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
            $table .= '<th>Status</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_ignore'), ENT_QUOTES) . '</th>';
            $table .= '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $url = (string) $row['url'];
                $count = (string) $row['count'];
                $status = (string) $row['status'];

                $detailUrl = \rex_url::backendController([
                    'page' => 'statistics/pages',
                    'url' => $url,
                    'date_start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'date_end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                    'httpstatus' => $httpstatus,
                ], false);
                $ignoreUrl = \rex_url::backendController([
                    'page' => 'statistics/pages',
                    'url' => $url,
                    'ignore_page' => true,
                    'date_start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'date_end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                    'httpstatus' => $httpstatus,
                ], false);
                $confirm = htmlspecialchars($url . ':' . PHP_EOL . $this->addon->i18n('statistics_confirm_ignore_delete'), ENT_QUOTES);

                $table .= '<tr>';
                $table .= '<td><a href="' . htmlspecialchars($detailUrl, ENT_QUOTES) . '">' . htmlspecialchars($url, ENT_QUOTES) . '</a></td>';
                $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
                $table .= '<td>' . htmlspecialchars($status, ENT_QUOTES) . '</td>';
                $table .= '<td><a href="' . htmlspecialchars($ignoreUrl, ENT_QUOTES) . '" data-confirm="' . $confirm . '">' . $this->addon->i18n('statistics_ignore_and_delete') . '</a></td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';
        }

        return $table;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws rex_sql_exception
     */
    private function getPageRows(string $httpstatus): array
    {
        $sql = rex_sql::factory();

        $query = 'SELECT agg.url, agg.count, IFNULL(us.status, "-") AS status '
            . 'FROM ('
            . ' SELECT url, IFNULL(SUM(count), 0) AS count'
            . ' FROM ' . rex::getTable('pagestats_visits_per_url')
            . ' WHERE date BETWEEN :start AND :end'
            . ' GROUP BY url'
            . ') agg ';

        if ('200' === $httpstatus) {
            $query .= 'INNER JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url AND us.status = "200 OK" ';
        } elseif ('not200' === $httpstatus) {
            $query .= 'INNER JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url AND us.status != "200 OK" ';
        } else {
            $query .= 'LEFT JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url ';
        }

        $query .= 'ORDER BY agg.count DESC';

        return $sql->getArray($query, [
            'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
            'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
        ]);
    }
}
