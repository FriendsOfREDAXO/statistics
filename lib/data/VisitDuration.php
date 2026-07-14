<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;

class VisitDuration
{
    /** @var null|array<int, array{timespan: string, count: int, dur: int}> */
    private ?array $rows = null;


    public function getChartData()
    {
        $res = $this->getRows();

        $labels = array_column($res, "count");
        $values = array_column($res, "timespan");

        return [
            "labels" => $labels,
            "values" => $values
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
            $table .= '<thead><tr><th>Dauer in Sekunden</th><th>Anzahl</th></tr></thead><tbody>';

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
        $this->rows = array_map(
            static fn(array $row): array => [
                'timespan' => (string) $row['timespan'],
                'count' => (int) $row['count'],
                'dur' => (int) $row['dur'],
            ],
            $sql->getArray("select '0 Sekunden' as timespan, count(*) as count, floor(visitduration / 30) as dur from " . rex::getTable("pagestats_sessionstats") . " where visitduration = 0 group by timespan, dur union select concat(floor(visitduration / 30) * 30, '-', (floor(visitduration / 30) + 1) * 30, ' Sekunden (~', floor(visitduration / 60) + 1, 'min)') as timespan, count(*) as count, floor(visitduration / 30) as dur from " . rex::getTable("pagestats_sessionstats") . " where visitduration > 0 group by timespan, dur order by dur asc")
        );

        return $this->rows;
    }
}
