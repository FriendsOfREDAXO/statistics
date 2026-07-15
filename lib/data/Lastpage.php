<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;

class Lastpage
{
    private ?DateFilter $filter_date_helper;
    /** @var null|array<int, array{lastpage: string, count: int}> */
    private ?array $rows = null;

    public function __construct(?DateFilter $filter_date_helper = null)
    {
        $this->filter_date_helper = $filter_date_helper;
    }


    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function getChartData(): array
    {
        $res = $this->getRows();

        $labels = array_column($res, "lastpage");
        $values = array_column($res, "count");

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     * @throws rex_exception 
     */
    public function getList(): string
    {
        $addon = rex_addon::get('statistics');

        $rows = $this->getRows();

        if ([] === $rows) {
            $table = rex_view::info($addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>Seite</th><th>Anzahl</th></tr></thead><tbody>';

            foreach ($rows as $row) {
                $lastpage = (string) $row['lastpage'];
                $count = (string) $row['count'];
                $table .= '<tr>';
                $table .= '<td>' . htmlspecialchars($lastpage, ENT_QUOTES) . '</td>';
                $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';
        }

        return $table;
    }

    /**
     * @return array<int, array{lastpage: string, count: int}>
     */
    private function getRows(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        $sql = rex_sql::factory();
        $query = 'SELECT lastpage, COUNT(*) AS count FROM ' . rex::getTable('pagestats_sessionstats');
        $params = [];

        if (null !== $this->filter_date_helper) {
            $query .= ' WHERE DATE(lastvisit) BETWEEN :start AND :end';
            $params = [
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ];
        }

        $query .= ' GROUP BY lastpage ORDER BY count DESC';

        $this->rows = array_map(
            static fn(array $row): array => [
                'lastpage' => (string) $row['lastpage'],
                'count' => (int) $row['count'],
            ],
            $sql->getArray($query, $params)
        );

        return $this->rows;
    }
}
