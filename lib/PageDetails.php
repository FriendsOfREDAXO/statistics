<?php

namespace AndiLeni\Statistics;

use DateInterval;
use DatePeriod;
use DateTime;
use rex;
use rex_sql;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Used on the page "pages.php" to handle and retreive data for a single url in the "details-section"
 *
 */
class PageDetails
{
    private string $url;
    private DateFilter $filter_date_helper;
    /** @var null|array<int, array{date: string, count: int}> */
    private ?array $detailRows = null;


    /**
     * 
     * 
     * @param string $url 
     * @param DateFilter $filterDateHelper 
     * @return void 
     */
    public function __construct(string $url, DateFilter $filterDateHelper)
    {
        $this->url = $url;
        $this->filter_date_helper = $filterDateHelper;
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getList(): string
    {
        $rows = $this->getDetailRows();

        if ([] === $rows) {
            return '';
        }

        $table = '<table class="table-bordered dt_order_first statistics_table table-striped table-hover table">';
        $table .= '<thead><tr><th>Datum</th><th>Anzahl</th></tr></thead><tbody>';

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
     * 
     * 
     * @return int 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getPageTotal(): int
    {
        $details_page_total = rex_sql::factory();

        $details_page_total->setQuery('SELECT sum(count) as "count" FROM ' . rex::getTable('pagestats_visits_per_url') . ' WHERE url = :url', ['url' => $this->url]);

        $details_page_total = $details_page_total->getValue('count') ? intval($details_page_total->getValue('count')) : 0;

        return $details_page_total;
    }


    /**
     *
     * @return array{labels: array<int, string>, values: array<int, string>}
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getSumPerDay(): array
    {
        // modify to include end date in period because SQL BETWEEN includes start and end date, but DatePeriod excludes end date
        // without modification an additional day would be fetched from database
        $period = new DatePeriod(
            $this->filter_date_helper->date_start,
            new DateInterval('P1D'),
            $this->filter_date_helper->date_end->modify('+1 day')
        );

        $array = [];

        foreach ($period as $value) {
            $array[$value->format("d.m.Y")] = "0";
        }

        $arr2 = [];

        foreach ($this->getDetailRows() as $row) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $row['date']);
            $date = false !== $dateObj ? $dateObj->format('d.m.Y') : $row['date'];
            $arr2[$date] = (string) $row['count'];
        }

        $data = array_merge($array, $arr2);

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
            'SELECT date, count FROM ' . rex::getTable('pagestats_visits_per_url')
            . ' WHERE url = :url AND date BETWEEN :start AND :end'
            . ' ORDER BY count DESC',
            [
                'url' => $this->url,
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
