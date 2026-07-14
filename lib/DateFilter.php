<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_addon_interface;
use rex_request;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;
use DateTimeImmutable;

/**
 * Helper class for handling date filters on backend pages
 * 
 */
class DateFilter
{

    public DateTimeImmutable $date_start;
    public DateTimeImmutable $date_end;

    public DateTimeImmutable $whole_time_start;

    /** @var non-empty-string */
    private string $table;
    private rex_addon_interface $addon;
    private const SESSION_KEY = 'statistics_datefilter';


    /**
     * 
     * 
     * @param string $date_start 
     * @param string $date_end 
     * @param string $table 
     * @return void 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    function __construct(string $date_start, string $date_end, string $table)
    {
        if ('' === $table) {
            throw new InvalidArgumentException('Table name must not be empty.');
        }

        /** @var non-empty-string $table */
        $this->table = $table;
        $this->addon = rex_addon::get('statistics');

        $hasRequestDateRange = '' !== $date_start && '' !== $date_end;

        if ($hasRequestDateRange) {
            $this->date_start = new DateTimeImmutable($date_start);
            $this->date_end = new DateTimeImmutable($date_end);

            rex_request::setSession(self::SESSION_KEY, [
                'date_start' => $this->date_start->format('Y-m-d'),
                'date_end' => $this->date_end->format('Y-m-d'),
            ]);
        } else {
            $sessionDateRange = rex_request::session(self::SESSION_KEY);
            $hasSessionDateRange = is_array($sessionDateRange)
                && isset($sessionDateRange['date_start'], $sessionDateRange['date_end'])
                && is_string($sessionDateRange['date_start'])
                && is_string($sessionDateRange['date_end'])
                && '' !== $sessionDateRange['date_start']
                && '' !== $sessionDateRange['date_end'];

            if ($hasSessionDateRange) {
                $this->date_start = new DateTimeImmutable($sessionDateRange['date_start']);
                $this->date_end = new DateTimeImmutable($sessionDateRange['date_end']);
            } else {

                // prefered date range
                $date_range = $this->addon->getConfig('statistics_default_datefilter_range');

                if ($date_range === 'last7days') {
                    $date = new DateTimeImmutable();
                    $date = $date->modify('-7 day');
                    $this->date_start = $date;
                } elseif ($date_range === 'last30days') {
                    $date = new DateTimeImmutable();
                    $date = $date->modify('-30 day');
                    $this->date_start = $date;
                } elseif ($date_range === 'thisYear') {
                    $date = new DateTimeImmutable();
                    $date = $date->modify('-365 day');
                    $this->date_start = $date;
                } else {
                    $this->date_start = $this->getMinDateFromTable();
                }
                // design decision, uncomment this line to default show only timespan where data was collected
                // $this->date_end = $this->getMaxDateFromTable();

                $this->date_end = new DateTimeImmutable();
                // $this->date_end->modify('+1 day');
            }
        }

        // set total time range to use in datefilter fragment with javascript
        $this->whole_time_start = $this->getMinDateFromTable();

        if ($this->date_start > $this->date_end) {
            echo rex_view::error($this->addon->i18n('statistics_dates'));
        }
    }


    /**
     * 
     * 
     * @return DateTimeImmutable 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    private function getMinDateFromTable(): DateTimeImmutable
    {
        $sql = rex_sql::factory();
        $result = $sql->setQuery('SELECT MIN(date) AS "date" from ' . rex::getTable($this->table));
        $minDateRaw = $result->getValue('date');

        if (!is_string($minDateRaw) || '' === $minDateRaw) {
            return new DateTimeImmutable('now');
        }

        $minDate = DateTimeImmutable::createFromFormat('Y-m-d', $minDateRaw);
        if (false === $minDate) {
            return new DateTimeImmutable('now');
        }

        return $minDate;
    }
}
