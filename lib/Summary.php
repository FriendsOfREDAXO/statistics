<?php

namespace AndiLeni\Statistics;

use rex;
use rex_sql;
use rex_sql_exception;
use InvalidArgumentException;


class Summary
{

    private DateFilter $filter_date_helper;


    /**
     * 
     * 
     * @param DateFilter $filter_date_helper 
     * @return void 
     */
    public function __construct(DateFilter $filter_date_helper)
    {
        $this->filter_date_helper = $filter_date_helper;
    }


    /**
     * 
     * 
        * @return array<string, int|float|string>
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getSummaryData(): array
    {
        $sql = rex_sql::factory();
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('-6 day'));
        $weekEnd = $today;

        $visits = $sql->getArray(
            'SELECT '
            . 'IFNULL(SUM(count), 0) AS total, '
            . 'IFNULL(SUM(CASE WHEN date = :today THEN count ELSE 0 END), 0) AS today, '
            . 'IFNULL(SUM(CASE WHEN date BETWEEN :start AND :end THEN count ELSE 0 END), 0) AS filtered '
            . 'FROM ' . rex::getTable('pagestats_visits_per_day'),
            [
                'today' => $today,
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ]
        );

        $visitors = $sql->getArray(
            'SELECT '
            . 'IFNULL(SUM(count), 0) AS total, '
            . 'IFNULL(SUM(CASE WHEN date = :today THEN count ELSE 0 END), 0) AS today, '
            . 'IFNULL(SUM(CASE WHEN date BETWEEN :start AND :end THEN count ELSE 0 END), 0) AS filtered '
            . 'FROM ' . rex::getTable('pagestats_visitors_per_day'),
            [
                'today' => $today,
                'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
            ]
        );

        $visitsRow = $visits[0] ?? ['total' => 0, 'today' => 0, 'filtered' => 0];
        $visitorsRow = $visitors[0] ?? ['total' => 0, 'today' => 0, 'filtered' => 0];

        $visitsWeek = $sql->getArray(
            'SELECT IFNULL(SUM(count), 0) AS total '
            . 'FROM ' . rex::getTable('pagestats_visits_per_day')
            . ' WHERE date BETWEEN :start AND :end',
            [
                'start' => $weekStart,
                'end' => $weekEnd,
            ]
        );

        $visitorsWeek = $sql->getArray(
            'SELECT IFNULL(SUM(count), 0) AS total '
            . 'FROM ' . rex::getTable('pagestats_visitors_per_day')
            . ' WHERE date BETWEEN :start AND :end',
            [
                'start' => $weekStart,
                'end' => $weekEnd,
            ]
        );

        $topArticle = $sql->getArray(
            'SELECT url, SUM(count) AS total '
            . 'FROM ' . rex::getTable('pagestats_visits_per_url')
            . ' WHERE date BETWEEN :start AND :end '
            . 'GROUP BY url ORDER BY total DESC LIMIT 1',
            [
                'start' => $weekStart,
                'end' => $weekEnd,
            ]
        );

        $weekVisits = (int) ($visitsWeek[0]['total'] ?? 0);
        $weekVisitors = (int) ($visitorsWeek[0]['total'] ?? 0);
        $weekPagesPerSession = $weekVisitors > 0 ? round($weekVisits / $weekVisitors, 2) : 0.0;

        $topArticleUrl = (string) ($topArticle[0]['url'] ?? '');
        $topArticleCount = (int) ($topArticle[0]['total'] ?? 0);
        $topArticlePath = $this->extractPathFromTrackedUrl($topArticleUrl);

        return [
            'visits_datefilter' => (int) $visitsRow['filtered'],
            'visitors_datefilter' => (int) $visitorsRow['filtered'],
            'visits_today' => (int) $visitsRow['today'],
            'visitors_today' => (int) $visitorsRow['today'],
            'visits_total' => (int) $visitsRow['total'],
            'visitors_total' => (int) $visitorsRow['total'],
            'visits_week' => $weekVisits,
            'visitors_week' => $weekVisitors,
            'top_article_path_week' => $topArticlePath,
            'top_article_count_week' => $topArticleCount,
            'pages_per_session_week' => $weekPagesPerSession,
        ];
    }

    private function extractPathFromTrackedUrl(string $url): string
    {
        if ('' === $url) {
            return '/';
        }

        $firstSlash = strpos($url, '/');
        if (false === $firstSlash) {
            return '/';
        }

        $path = substr($url, $firstSlash);
        if ('' === $path) {
            return '/';
        }

        return $path;
    }
}
