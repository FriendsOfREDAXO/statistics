<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use InvalidArgumentException;
use rex_exception;
use rex_sql;
use rex_view;

class Country
{
    /** @var null|array<int, array{name: string, count: int}> */
    private ?array $rows = null;

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function getRows(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        $sql = rex_sql::factory();
        $this->rows = array_map(
            static fn(array $row): array => ['name' => (string) $row['name'], 'count' => (int) $row['count']],
            $sql->getArray('SELECT name, count FROM ' . rex::getTable('pagestats_data') . ' where type = "country" ORDER BY count DESC')
        );

        return $this->rows;
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function getChartData(): array
    {
        $res = $this->getRows();

        $labels = array_column($res, "name");
        $values = array_column($res, "count");

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

        if ([] === $rows) {
            $table = rex_view::info($addon->i18n('statistics_no_data'));
        } else {
            $table = '<table class="dt_order_second statistics_table table table-striped table-hover">';
            $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_name'), ENT_QUOTES) . '</th><th>Anzahl</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $table .= '<tr><td>' . htmlspecialchars($row['name'], ENT_QUOTES) . '</td><td data-sort="' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '</td></tr>';
            }
            $table .= '</tbody></table>';
        }

        return $table;
    }
}
