<?php

namespace AndiLeni\Statistics;

use DateInterval;
use DatePeriod;
use DateTime;
use InvalidArgumentException;
use rex;
use rex_addon;
use rex_sql;
use rex_sql_exception;

/**
 * Used on the page "referer.php" to handle and retrieve data for a single referer.
 */
class RefererDetails
{
    private string $referer;
    private DateFilter $filter_date_helper;
    /** @var null|array<int, array{date: string, count: int}> */
    private ?array $detailRows = null;

    /**
     * @param DateFilter $filter_date_helper
     * @throws InvalidArgumentException
     */
    public function __construct(string $referer, DateFilter $filter_date_helper)
    {
        $this->referer = $referer;
        $this->filter_date_helper = $filter_date_helper;
    }

    /**
     * @return string
     * @throws rex_sql_exception
     */
    public function getList(): string
    {
        $rows = $this->getDetailRows();

        if ([] === $rows) {
            return '';
        }

        $addon = rex_addon::get('statistics');

        $table = '<table class="table-bordered dt_order_first statistics_table table-striped table-hover table">';
        $table .= '<thead><tr><th>' . htmlspecialchars($addon->i18n('statistics_date'), ENT_QUOTES) . '</th><th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $formattedDateObj = DateTime::createFromFormat('Y-m-d', $row['date']);
            $formattedDate = false !== $formattedDateObj ? $formattedDateObj->format('d.m.Y') : $row['date'];
            $table .= '<tr>';
            $table .= '<td data-sort="' . htmlspecialchars($row['date'], ENT_QUOTES) . '">' . htmlspecialchars($formattedDate, ENT_QUOTES) . '</td>';
            $table .= '<td data-sort="' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $row['count'], ENT_QUOTES) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table>';

        return $table;
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, string>}
     * @throws rex_sql_exception
     */
    public function getSumPerDay(): array
    {
        $period = new DatePeriod(
            $this->filter_date_helper->date_start,
            new DateInterval('P1D'),
            $this->filter_date_helper->date_end->modify('+1 day')
        );

        $defaults = [];
        foreach ($period as $value) {
            $defaults[$value->format('d.m.Y')] = '0';
        }

        $values = [];
        foreach ($this->getDetailRows() as $row) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $row['date']);
            $date = false !== $dateObj ? $dateObj->format('d.m.Y') : $row['date'];
            $values[$date] = (string) $row['count'];
        }

        $data = [] !== $values ? array_merge($defaults, $values) : $defaults;

        return [
            'labels' => array_keys($data),
            'values' => array_values($data),
        ];
    }

    /**
     * @return array<int, array{date: string, count: int}>
     * @throws rex_sql_exception
     */
    private function getDetailRows(): array
    {
        if (null !== $this->detailRows) {
            return $this->detailRows;
        }

        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT date, count FROM ' . rex::getTable('pagestats_referer')
            . ' WHERE referer = :referer AND date BETWEEN :start AND :end'
            . ' GROUP BY date ORDER BY count DESC',
            [
                'referer' => $this->referer,
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ]
        );

        $this->detailRows = array_map(
            static fn(array $row): array => [
                'date' => (string) $row['date'],
                'count' => (int) $row['count'],
            ],
            $rows
        );

        return $this->detailRows;
    }
}