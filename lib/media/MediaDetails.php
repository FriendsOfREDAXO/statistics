<?php

namespace AndiLeni\Statistics;

use rex;
use rex_sql;
use InvalidArgumentException;
use rex_sql_exception;
use AndiLeni\Statistics\DateFilter;
use DateInterval;
use DatePeriod;
use DateTime;

/**
 * Used on the page "media.php" to handle and retreive data for a single media url
 *
 */
class MediaDetails
{
    private string $url;
    private DateFilter $filter_date_helper;
    /** @var null|array<int, array{date: string, count: int}> */
    private ?array $detailRows = null;


    /**
     * 
     * 
     * @param string $url 
    * @param DateFilter $filter_date_helper
     * @return void 
     */
    public function __construct(string $url, DateFilter $filter_date_helper)
    {
        $this->url = $url;
        $this->filter_date_helper = $filter_date_helper;
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
            'SELECT date, count FROM ' . rex::getTable('pagestats_media')
            . ' WHERE url = :url AND date BETWEEN :start AND :end'
            . ' GROUP BY date ORDER BY date ASC',
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
