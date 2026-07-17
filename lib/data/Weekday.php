<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_addon_interface;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;


/**
 * Handles the "weekday" data for statistics
 *
 */
class Weekday
{

    private rex_addon_interface $addon;
    /** @var null|array<int, array{name: string, count: int}> */
    private ?array $rows = null;


    /**
     * 
     * 
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct()
    {
        $this->addon = rex_addon::get('statistics');
    }


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
            $sql->getArray('SELECT name, count FROM ' . rex::getTable('pagestats_data') . ' WHERE type = "weekday" ORDER BY count DESC')
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
        $data = [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
        ];

        foreach ($this->getRows() as $row) {
            $data[(int) $row['name'] - 1] = $row['count'];
        }

        return $data;
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     */
    public function getList(): string
    {
        $rows = $this->getRows();

        if ([] === $rows) {
            $table = rex_view::info($this->addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>' . htmlspecialchars($this->addon->i18n('statistics_name'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($this->addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $weekday = $this->formatWeekday((int) $row['name']);
                $count = (string) $row['count'];
                $table .= '<tr><td data-sort="' . htmlspecialchars($row['name'], ENT_QUOTES) . '">' . htmlspecialchars($weekday, ENT_QUOTES) . '</td><td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td></tr>';
            }
            $table .= '</tbody></table>';
        }

        return $table;
    }

    private function formatWeekday(int $weekday): string
    {
        return match ($weekday) {
            1 => $this->addon->i18n('statistics_monday'),
            2 => $this->addon->i18n('statistics_tuesday'),
            3 => $this->addon->i18n('statistics_wednesday'),
            4 => $this->addon->i18n('statistics_thursday'),
            5 => $this->addon->i18n('statistics_friday'),
            6 => $this->addon->i18n('statistics_saturday'),
            7 => $this->addon->i18n('statistics_sunday'),
            default => (string) $weekday,
        };
    }
}
