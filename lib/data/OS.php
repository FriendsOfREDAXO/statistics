<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Handles the devices-"os" data for statistics
 *
 */
class OS
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
            $sql->getArray('SELECT name, count FROM ' . rex::getTable('pagestats_data') . ' WHERE type = "os" ORDER BY count DESC')
        );

        return $this->rows;
    }


    /**
     *
     * @return array<int, array{name: string, value: int}>
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getData(): array
    {
        $data = [];

        foreach ($this->getRows() as $row) {
            $data[] = [
                'name' => $row['name'],
                'value' => $row['count']
            ];
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
        $addon = rex_addon::get('statistics');
        $rows = $this->getRows();

        if ([] === $rows) {
            $table = rex_view::info($addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_name'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $table .= '<tr><td>' . htmlspecialchars($row['name'], ENT_QUOTES) . '</td><td data-sort="' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '</td></tr>';
            }
            $table .= '</tbody></table>';
        }

        return $table;
    }
}
