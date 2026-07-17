<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Handles the "hour" data for statistics
 *
 */
class Hour
{
    /** @var null|array<int, array{name: string, count: int}> */
    private ?array $rows = null;

    /**
     * 
     * 
     * @return array<int, array{name: string, count: int}>
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    private function getRows(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        $sql = rex_sql::factory();
        $this->rows = array_map(
            static fn(array $row): array => ['name' => (string) $row['name'], 'count' => (int) $row['count']],
            $sql->getArray('SELECT name, count FROM ' . rex::getTable('pagestats_data') . ' WHERE type = "hour" ORDER BY count DESC')
        );

        return $this->rows;
    }


    /**
     *
     * @return array<int, int>
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getData(): array
    {
        $hours = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 13 => 0, 14 => 0, 15 => 0, 16 => 0, 17 => 0, 18 => 0, 19 => 0, 20 => 0, 21 => 0, 22 => 0, 23 => 0];

        foreach ($this->getRows() as $row) {
            $hours[(int) $row['name']] = $row['count'];
        }

        return $hours;
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     */
    public function getList(): string
    {
        $addon = rex_addon::get('statistics');
        $rows = $this->getRows();

        if ([] === $rows) {
            $table = rex_view::info($addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_name'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $hour = str_pad($row['name'], 2, '0', STR_PAD_LEFT) . ' Uhr';
                $count = (string) $row['count'];
                $table .= '<tr><td data-sort="' . htmlspecialchars($row['name'], ENT_QUOTES) . '">' . htmlspecialchars($hour, ENT_QUOTES) . '</td><td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td></tr>';
            }
            $table .= '</tbody></table>';
        }

        return $table;
    }
}
