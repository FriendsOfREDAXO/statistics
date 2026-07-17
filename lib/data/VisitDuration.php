<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use InvalidArgumentException;
use rex_exception;
use rex_sql;
use rex_view;

class VisitDuration
{
    private ?DateFilter $filter_date_helper;
    /** @var null|array<int, array{timespan: string, count: int, dur: int}> */
    private ?array $rows = null;

    public function __construct(?DateFilter $filter_date_helper = null)
    {
        $this->filter_date_helper = $filter_date_helper;
    }


    /**
     * @return array{labels: array<int, int>, values: array<int, string>}
     */
    public function getChartData(): array
    {
        $res = $this->getRows();

        $labels = array_column($res, "count");
        $values = array_column($res, "timespan");

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

        $note = '<div class="alert alert-warning" style="margin-bottom:10px;">'
            . htmlspecialchars($addon->i18n('statistics_visit_duration_zero_seconds_note'), ENT_QUOTES)
            . '</div>';

        if ([] === $rows) {
            $table = $note . rex_view::info($addon->i18n('statistics_no_data'));
        } else {
            $table = $note;
            $table .= '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_duration_seconds_label'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';

            foreach ($rows as $row) {
                $timespan = (string) $row['timespan'];
                $count = (string) $row['count'];
                $dur = (string) $row['dur'];
                $table .= '<tr>';
                $table .= '<td data-sort="' . htmlspecialchars($dur, ENT_QUOTES) . '">' . htmlspecialchars($timespan, ENT_QUOTES) . '</td>';
                $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';
        }

        return $table;
    }

    /**
     * @return array<int, array{timespan: string, count: int, dur: int}>
     */
    private function getRows(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        $sql = rex_sql::factory();
        $where = '';
        $params = [];

        if (null !== $this->filter_date_helper) {
            $where = ' AND DATE(lastvisit) BETWEEN :start AND :end';
            $params = [
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ];
        }

        $sessionTable = rex::getTable('pagestats_sessionstats');
        $query = 'SELECT "0 Sekunden" AS timespan, COUNT(*) AS count, FLOOR(visitduration / 30) AS dur '
            . 'FROM ' . $sessionTable . ' WHERE visitduration = 0' . $where . ' '
            . 'GROUP BY timespan, dur '
            . 'UNION '
            . 'SELECT CONCAT(FLOOR(visitduration / 30) * 30, "-", (FLOOR(visitduration / 30) + 1) * 30, " Sekunden (~", FLOOR(visitduration / 60) + 1, "min)") AS timespan, '
            . 'COUNT(*) AS count, FLOOR(visitduration / 30) AS dur '
            . 'FROM ' . $sessionTable . ' WHERE visitduration > 0' . $where . ' '
            . 'GROUP BY timespan, dur '
            . 'ORDER BY dur ASC';

        $this->rows = array_map(
            static fn(array $row): array => [
                'timespan' => (string) $row['timespan'],
                'count' => (int) $row['count'],
                'dur' => (int) $row['dur'],
            ],
            $sql->getArray($query, $params)
        );

        return $this->rows;
    }
}
