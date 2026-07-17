<?php

namespace AndiLeni\Statistics;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use rex;
use rex_addon;
use rex_addon_interface;
use rex_sql;
use rex_sql_exception;

class chartData
{
    private DateFilter $filter_date_helper;
    private rex_addon_interface $addon;

    /**
     *
     * @throws InvalidArgumentException
     */
    public function __construct(DateFilter $filter_date_helper)
    {
        $this->filter_date_helper = $filter_date_helper;
        $this->addon = rex_addon::get('statistics');
    }

    /**
     * @return array<int, string>
     */
    private function getLabels(): array
    {
        $period = new DatePeriod(
            $this->filter_date_helper->date_start,
            new DateInterval('P1D'),
            $this->filter_date_helper->date_end->modify('+1 day')
        );

        $labels = [];
        foreach ($period as $value) {
            $labels[] = $value->format('d.m.Y');
        }

        return $labels;
    }

    /**
        * @return array{series: array<int, array<string, mixed>>, legend: array<int, mixed>, xaxis: array<int, string>}
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getMainChartData(): array
    {
        $data_chart = array_merge($this->getVisitsPerDay(), $this->getVisitorsPerDay());

        return [
            'series' => $data_chart,
            'legend' => array_column($data_chart, 'name'),
            'xaxis' => $this->getLabels(),
        ];
    }

    /**
        * @return array<int, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    private function getVisitsPerDay(): array
    {
        return $this->getDailySeries('pagestats_visits_per_day', 'Aufrufe Gesamt', 'Aufrufe ');
    }

    /**
        * @return array<int, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    private function getVisitorsPerDay(): array
    {
        return $this->getDailySeries('pagestats_visitors_per_day', 'Besucher Gesamt', 'Besucher ');
    }

    /**
        * @return array{data: array<int, array{0: string, 1: int}>, max: int}
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getHeatmapVisits(): array
    {
        $sql = rex_sql::factory();

        $jan_first = new DateTimeImmutable('first day of january this year');
        $dec_last = new DateTimeImmutable('first day of january next year');
        $visits_per_day = $sql->getArray(
            'SELECT date, IFNULL(SUM(count),0) AS count FROM ' . rex::getTable('pagestats_visits_per_day')
            . ' WHERE date BETWEEN :start AND :end GROUP BY date ORDER BY date ASC',
            ['start' => $jan_first->format('Y-m-d'), 'end' => $dec_last->format('Y-m-d')]
        );

        $heatmap_calendar = [];
        foreach ($visits_per_day as $row) {
            $dateKey = (string) ($row['date'] ?? '');
            if ('' === $dateKey) {
                continue;
            }

            $heatmap_calendar[$dateKey] = (int) ($row['count'] ?? 0);
        }

        $period = new DatePeriod($jan_first, new DateInterval('P1D'), $dec_last);
        $data_visits_heatmap_values = [];
        foreach ($period as $value) {
            $key = $value->format('Y-m-d');
            $data_visits_heatmap_values[] = [$key, $heatmap_calendar[$key] ?? 0];
        }

        return [
            'data' => $data_visits_heatmap_values,
            'max' => [] === $heatmap_calendar ? 0 : max(array_values($heatmap_calendar)),
        ];
    }

    /**
        * @return array{series: array<int, array<string, mixed>>, legend: array<int, mixed>, xaxis: array<int, string>}
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getChartDataMonthly(): array
    {
        [$min_date, $max_date] = $this->getHistoryMinMaxDates();

        $period = new DatePeriod($min_date, new DateInterval('P1M'), $max_date->modify('+1 month'));
        $period_map = $this->createPeriodValueMap($period, 'Y-m', 0);

        $xaxis = array_map(
            static function (string $key): string {
                $date = DateTimeImmutable::createFromFormat('Y-m', $key);

                return false !== $date ? $date->format('M Y') : $key;
            },
            array_keys($period_map)
        );

        $series = array_merge(
            $this->getMonthlySeries('pagestats_visits_per_day', $period_map, 'Aufrufe Gesamt', 'Aufrufe '),
            $this->getMonthlySeries('pagestats_visitors_per_day', $period_map, 'Besucher Gesamt', 'Besucher ')
        );

        return [
            'series' => $series,
            'legend' => array_column($series, 'name'),
            'xaxis' => $xaxis,
        ];
    }

    /**
        * @return array{series: array<int, array<string, mixed>>, legend: array<int, mixed>, xaxis: array<int, string>}
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function getChartDataYearly(): array
    {
        [$min_date, $max_date] = $this->getHistoryMinMaxDates();

        $period = new DatePeriod($min_date, new DateInterval('P1Y'), $max_date->modify('+1 year'));
        $period_map = $this->createPeriodValueMap($period, 'Y', 0);

        $series = array_merge(
            $this->getYearlySeries('pagestats_visits_per_day', $period_map, 'Aufrufe Gesamt', 'Aufrufe '),
            $this->getYearlySeries('pagestats_visitors_per_day', $period_map, 'Besucher Gesamt', 'Besucher ')
        );

        return [
            'series' => $series,
            'legend' => array_column($series, 'name'),
            'xaxis' => array_keys($period_map),
        ];
    }

    /**
        * @param iterable<DateTimeInterface> $period
     * @return array<string, int>
     */
    private function createPeriodValueMap(iterable $period, string $format, int $initialValue = 0): array
    {
        $values = [];
        foreach ($period as $value) {
            $values[$value->format($format)] = $initialValue;
        }

        return $values;
    }

    /**
     * @return list<string>
     * @throws rex_sql_exception
     */
    private function getDomains(string $table): array
    {
        $table = trim($table);
        if ('' === $table) {
            return [];
        }

        $sql = rex_sql::factory();
        $domains = $sql->getArray('SELECT DISTINCT domain FROM ' . rex::getTable($table) . ' ORDER BY domain ASC');

        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['domain'] ?? ''), $domains), static fn(string $domain): bool => '' !== $domain));
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     * @throws rex_sql_exception
     */
    private function getHistoryMinMaxDates(): array
    {
        $sql = rex_sql::factory();
        $min_max_date = $sql->getArray(
            'SELECT MIN(min_date) AS min_date, MAX(max_date) AS max_date FROM ('
            . ' SELECT MIN(date) AS min_date, MAX(date) AS max_date FROM ' . rex::getTable('pagestats_visits_per_day')
            . ' UNION ALL '
            . ' SELECT MIN(date) AS min_date, MAX(date) AS max_date FROM ' . rex::getTable('pagestats_visitors_per_day')
            . ' ) history'
        );

        if ([] === $min_max_date || null === $min_max_date[0]['min_date']) {
            return [new DateTimeImmutable(), new DateTimeImmutable()];
        }

        $minDateRaw = (string) $min_max_date[0]['min_date'];
        $maxDateRaw = (string) $min_max_date[0]['max_date'];
        $minDate = DateTimeImmutable::createFromFormat('Y-m-d', $minDateRaw);
        $maxDate = DateTimeImmutable::createFromFormat('Y-m-d', $maxDateRaw);

        return [
            false !== $minDate ? $minDate : new DateTimeImmutable(),
            false !== $maxDate ? $maxDate : new DateTimeImmutable(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    private function getDailySeries(string $table, string $totalName, string $domainNamePrefix): array
    {
        $table = trim($table);
        if ('' === $table) {
            return [];
        }

        $period = new DatePeriod(
            $this->filter_date_helper->date_start,
            new DateInterval('P1D'),
            $this->filter_date_helper->date_end->modify('+1 day')
        );
        $base_values = $this->createPeriodValueMap($period, 'd.m.Y');
        $total_values = $base_values;

        $combine_all_domains = (bool) $this->addon->getConfig('statistics_combine_all_domains');
        $domain_values = [];
        if (!$combine_all_domains) {
            foreach ($this->getDomains($table) as $domain) {
                $domain_values[$domain] = $base_values;
            }
        }

        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT date, domain, count FROM ' . rex::getTable($table) . ' WHERE date BETWEEN :start AND :end ORDER BY date ASC',
            [
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ]
        );

        foreach ($rows as $row) {
            $dateRaw = (string) ($row['date'] ?? '');
            $dateObj = DateTime::createFromFormat('Y-m-d', $dateRaw);
            $date = false !== $dateObj ? $dateObj->format('d.m.Y') : $dateRaw;
            $count = (int) $row['count'];
            $domain = (string) ($row['domain'] ?? '');

            if ('' === $date || '' === $domain) {
                continue;
            }

            $total_values[$date] += $count;

            if (!$combine_all_domains) {
                if (!isset($domain_values[$domain])) {
                    $domain_values[$domain] = $base_values;
                }

                $domain_values[$domain][$date] += $count;
            }
        }

        $series = [[
            'data' => array_values($total_values),
            'name' => $totalName,
            'type' => 'line',
        ]];

        if (!$combine_all_domains) {
            foreach ($domain_values as $domain => $values) {
                $series[] = [
                    'data' => array_values($values),
                    'name' => $domainNamePrefix . $domain,
                    'type' => 'line',
                ];
            }
        }

        return $series;
    }

    /**
     * @param array<string, int> $period_map
     * @return array<int, array<string, mixed>>
     * @throws rex_sql_exception
     */
    private function getMonthlySeries(string $table, array $period_map, string $totalName, string $domainNamePrefix): array
    {
        $table = trim($table);
        if ('' === $table) {
            return [];
        }

        $total_values = $period_map;
        $combine_all_domains = (bool) $this->addon->getConfig('statistics_combine_all_domains');
        $domain_values = [];

        if (!$combine_all_domains) {
            foreach ($this->getDomains($table) as $domain) {
                $domain_values[$domain] = $period_map;
            }
        }

        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT YEAR(date) AS year, MONTH(date) AS month, domain, SUM(count) AS count '
            . 'FROM ' . rex::getTable($table) . ' '
            . 'GROUP BY year, month, domain '
            . 'ORDER BY year ASC, month ASC'
        );

        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', (int) $row['year'], (int) $row['month']);
            $count = (int) $row['count'];
            $domain = (string) ($row['domain'] ?? '');

            if ('' === $domain) {
                continue;
            }

            if (!array_key_exists($key, $total_values)) {
                continue;
            }

            $total_values[$key] += $count;

            if (!$combine_all_domains) {
                if (!isset($domain_values[$domain])) {
                    $domain_values[$domain] = $period_map;
                }

                $domain_values[$domain][$key] += $count;
            }
        }

        $series = [[
            'data' => array_values($total_values),
            'name' => $totalName,
            'type' => 'line',
        ]];

        if (!$combine_all_domains) {
            foreach ($domain_values as $domain => $values) {
                $series[] = [
                    'data' => array_values($values),
                    'name' => $domainNamePrefix . $domain,
                    'type' => 'line',
                ];
            }
        }

        return $series;
    }

    /**
     * @param array<string, int> $period_map
     * @return array<int, array<string, mixed>>
     * @throws rex_sql_exception
     */
    private function getYearlySeries(string $table, array $period_map, string $totalName, string $domainNamePrefix): array
    {
        $table = trim($table);
        if ('' === $table) {
            return [];
        }

        $total_values = $period_map;
        $combine_all_domains = (bool) $this->addon->getConfig('statistics_combine_all_domains');
        $domain_values = [];

        if (!$combine_all_domains) {
            foreach ($this->getDomains($table) as $domain) {
                $domain_values[$domain] = $period_map;
            }
        }

        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT YEAR(date) AS year, domain, SUM(count) AS count '
            . 'FROM ' . rex::getTable($table) . ' '
            . 'GROUP BY year, domain '
            . 'ORDER BY year ASC'
        );

        foreach ($rows as $row) {
            $key = (string) $row['year'];
            $count = (int) $row['count'];
            $domain = (string) ($row['domain'] ?? '');

            if ('' === $domain) {
                continue;
            }

            if (!array_key_exists($key, $total_values)) {
                continue;
            }

            $total_values[$key] += $count;

            if (!$combine_all_domains) {
                if (!isset($domain_values[$domain])) {
                    $domain_values[$domain] = $period_map;
                }

                $domain_values[$domain][$key] += $count;
            }
        }

        $series = [[
            'data' => array_values($total_values),
            'name' => $totalName,
            'type' => 'line',
        ]];

        if (!$combine_all_domains) {
            foreach ($domain_values as $domain => $values) {
                $series[] = [
                    'data' => array_values($values),
                    'name' => $domainNamePrefix . $domain,
                    'type' => 'line',
                ];
            }
        }

        return $series;
    }
}
