<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Handles the device-"model" data for statistics
 *
 */
class Model
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

        $this->rows = DataTypeAggregationRepository::getRowsByType('model');

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
            $table .= '<thead><tr><th>' . rex_escape($addon->i18n('statistics_name')) . '</th><th>' . rex_escape($addon->i18n('statistics_count')) . '</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $table .= '<tr><td>' . rex_escape($row['name']) . '</td><td data-sort="' . rex_escape((string) $row['count']) . '">' . rex_escape((string) $row['count']) . '</td></tr>';
            }
            $table .= '</tbody></table>';
        }

        return $table;
    }
}
