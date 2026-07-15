<?php

namespace AndiLeni\Statistics;

use rex;
use rex_addon;
use rex_sql;
use rex_view;
use InvalidArgumentException;
use rex_sql_exception;

/**
 * Helper class for the backend page "pages"
 * 
 */
class Pages
{

    private rex_addon $addon;
    private DateFilter $filter_date_helper;


    /**
     * 
     * 
     * @param DateFilter $filter_date_helper 
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct(DateFilter $filter_date_helper)
    {
        $this->addon = rex_addon::get('statistics');
        $this->filter_date_helper = $filter_date_helper;
    }


    /**
     * 
     * 
     * @return array 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function sumPerPage(string $httpstatus, int $limit = 30): array
    {
        return $this->getPageRows($httpstatus, $limit);
    }

    /**
     * @return list<string>
     */
    public function getFavoriteUrls(): array
    {
        $raw = (string) $this->addon->getConfig('statistics_favorite_urls', '');
        if ('' === trim($raw)) {
            return [];
        }

        $lines = explode("\n", str_replace("\r", '', $raw));
        $favorites = [];

        foreach ($lines as $line) {
            $url = trim((string) $line);
            if ('' !== $url) {
                $favorites[$url] = true;
            }
        }

        return array_keys($favorites);
    }

    public function toggleFavoriteUrl(string $url): bool
    {
        $url = trim($url);
        if ('' === $url) {
            return false;
        }

        $favorites = $this->getFavoriteUrls();
        $isFavorite = in_array($url, $favorites, true);

        if ($isFavorite) {
            $favorites = array_values(array_filter($favorites, static fn(string $item): bool => $item !== $url));
            $this->addon->setConfig('statistics_favorite_urls', implode(PHP_EOL, $favorites));

            return false;
        }

        $favorites[] = $url;
        $this->addon->setConfig('statistics_favorite_urls', implode(PHP_EOL, array_values(array_unique($favorites))));

        return true;
    }



    /**
     * 
     * 
     * @param string $request_url 
     * @return int 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function ignorePage(string $request_url): int
    {
        $ignored_paths = $this->addon->getConfig('statistics_ignored_paths');
        if ($ignored_paths == "") {
            $this->addon->setConfig('statistics_ignored_paths', $request_url);
        } else {
            $this->addon->setConfig('statistics_ignored_paths', $ignored_paths . PHP_EOL . $request_url);
        }

        $sql = rex_sql::factory();

        // get sum per day for substraction
        $sum_per_day = $sql->getArray('select date, sum(count) as "count" from ' . rex::getTable('pagestats_visits_per_url') . ' where url = :url group by date', ['url' => $request_url]);

        // reduce visits per day by these factors
        foreach ($sum_per_day as $e) {
            $sql->setQuery('update ' . rex::getTable('pagestats_visits_per_day') . ' set count = count - :v where date = :date', ['v' => $e['count'], 'date' => $e['date']]);
        }

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visits_per_url') . ' where url = :url', ['url' => $request_url]);

        return $sql->getRows() ?? 0;
    }


    /**
     * 
     * 
     * @return string 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function getList(string $httpstatus, int $limit = 500, bool $onlyFavorites = false): string
    {
        $visitorsPerUrlEnabled = (bool) $this->addon->getConfig('statistics_pages_visitors_enabled', false);
        $favoriteUrls = $this->getFavoriteUrls();
        $favoriteMap = array_fill_keys($favoriteUrls, true);
        $rows = $this->getPageRows($httpstatus, $limit, $favoriteUrls, $onlyFavorites, $visitorsPerUrlEnabled);

        $visitsNoteKey = $visitorsPerUrlEnabled ? 'statistics_pages_visits_note' : 'statistics_pages_visits_note_legacy';
        $visitsNote = '<div class="alert alert-info" style="margin-bottom:10px;">'
            . htmlspecialchars($this->addon->i18n($visitsNoteKey), ENT_QUOTES)
            . '</div>';

        if ([] === $rows) {
            $table = $visitsNote . rex_view::info($this->addon->i18n('statistics_no_data'));
        } else {
            $table = $visitsNote;

            if ($limit > 0 && count($rows) >= $limit) {
                $table .= rex_view::warning(sprintf($this->addon->i18n('statistics_pages_list_limited'), (string) $limit));
            }

            $table .= '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table" data-page-length="30">';
            $table .= '<thead><tr>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_favorite'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_url'), ENT_QUOTES) . '</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_pages_visits_column'), ENT_QUOTES) . '</th>';
            if ($visitorsPerUrlEnabled) {
                $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_pages_visitors_column'), ENT_QUOTES) . '</th>';
            }
            $table .= '<th>Status</th>';
            $table .= '<th>' . htmlspecialchars($this->addon->i18n('statistics_ignore'), ENT_QUOTES) . '</th>';
            $table .= '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $url = (string) $row['url'];
                $count = (string) $row['count'];
                $visitors = (string) ($row['visitors'] ?? '0');
                $status = (string) $row['status'];
                $isFavorite = isset($favoriteMap[$url]);

                $detailUrl = \rex_url::backendController([
                    'page' => 'statistics/pages',
                    'url' => $url,
                    'date_start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'date_end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                    'httpstatus' => $httpstatus,
                ], false);
                $ignoreUrl = \rex_url::backendController([
                    'page' => 'statistics/pages',
                    'url' => $url,
                    'ignore_page' => true,
                    'only_favorites' => $onlyFavorites ? 1 : 0,
                    'date_start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'date_end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                    'httpstatus' => $httpstatus,
                ], false);
                $toggleFavoriteUrl = \rex_url::backendController([
                    'page' => 'statistics/pages',
                    'url' => $url,
                    'toggle_favorite' => true,
                    'only_favorites' => $onlyFavorites ? 1 : 0,
                    'date_start' => $this->filter_date_helper->date_start->format('Y-m-d'),
                    'date_end' => $this->filter_date_helper->date_end->format('Y-m-d'),
                    'httpstatus' => $httpstatus,
                ], false);
                $confirm = htmlspecialchars($url . ':' . PHP_EOL . $this->addon->i18n('statistics_confirm_ignore_delete'), ENT_QUOTES);
                $star = $isFavorite ? '★' : '☆';
                $favoriteTitle = $isFavorite
                    ? $this->addon->i18n('statistics_favorite_toggle_remove')
                    : $this->addon->i18n('statistics_favorite_toggle_add');
                $rowClass = $isFavorite ? ' style="background-color:#fff9e6;"' : '';

                $table .= '<tr' . $rowClass . '>';
                $table .= '<td data-sort="' . ($isFavorite ? '0' : '1') . '"><a href="' . htmlspecialchars($toggleFavoriteUrl, ENT_QUOTES) . '" title="' . htmlspecialchars($favoriteTitle, ENT_QUOTES) . '" style="text-decoration:none;font-size:18px;line-height:1;">' . $star . '</a></td>';
                $table .= '<td><a href="' . htmlspecialchars($detailUrl, ENT_QUOTES) . '">' . htmlspecialchars($url, ENT_QUOTES) . '</a></td>';
                $table .= '<td data-sort="' . htmlspecialchars($count, ENT_QUOTES) . '">' . htmlspecialchars($count, ENT_QUOTES) . '</td>';
                if ($visitorsPerUrlEnabled) {
                    $table .= '<td data-sort="' . htmlspecialchars($visitors, ENT_QUOTES) . '">' . htmlspecialchars($visitors, ENT_QUOTES) . '</td>';
                }
                $table .= '<td>' . htmlspecialchars($status, ENT_QUOTES) . '</td>';
                $table .= '<td><a href="' . htmlspecialchars($ignoreUrl, ENT_QUOTES) . '" data-confirm="' . $confirm . '">' . $this->addon->i18n('statistics_ignore_and_delete') . '</a></td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';
        }

        return $table;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws rex_sql_exception
     */
    private function getPageRows(string $httpstatus, int $limit = 0, array $favoriteUrls = [], bool $onlyFavorites = false, bool $visitorsPerUrlEnabled = false): array
    {
        $sql = rex_sql::factory();

        $query = 'SELECT agg.url, agg.count';
        if ($visitorsPerUrlEnabled) {
            $query .= ', IFNULL(vis.unique_visitors, 0) AS visitors';
        }
        $query .= ', IFNULL(us.status, "-") AS status '
            . 'FROM ('
            . ' SELECT url, IFNULL(SUM(count), 0) AS count'
            . ' FROM ' . rex::getTable('pagestats_visits_per_url')
            . ' WHERE date BETWEEN :start AND :end'
            . ' GROUP BY url'
            . ') agg ';

        if ($visitorsPerUrlEnabled) {
            $query .= 'LEFT JOIN ('
                . ' SELECT url, IFNULL(SUM(count), 0) AS unique_visitors'
                . ' FROM ' . rex::getTable('pagestats_visitors_per_url')
                . ' WHERE date BETWEEN :start AND :end'
                . ' GROUP BY url'
                . ') vis ON vis.url = agg.url ';
        }

        if ('200' === $httpstatus) {
            $query .= 'INNER JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url AND us.status = "200 OK" ';
        } elseif ('not200' === $httpstatus) {
            $query .= 'INNER JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url AND us.status != "200 OK" ';
        } else {
            $query .= 'LEFT JOIN ' . rex::getTable('pagestats_urlstatus') . ' us ON agg.url = us.url ';
        }

        $params = [
            'start' => $this->filter_date_helper->date_start->format('Y-m-d'),
            'end' => $this->filter_date_helper->date_end->format('Y-m-d'),
        ];

        if ([] !== $favoriteUrls) {
            $favoritePlaceholders = [];
            foreach (array_values($favoriteUrls) as $index => $favoriteUrl) {
                $key = 'fav' . $index;
                $favoritePlaceholders[] = ':' . $key;
                $params[$key] = $favoriteUrl;
            }

            if ($onlyFavorites) {
                $query .= 'WHERE agg.url IN (' . implode(', ', $favoritePlaceholders) . ') ';
                $query .= 'ORDER BY agg.count DESC';
            } else {
                $query .= 'ORDER BY CASE WHEN agg.url IN (' . implode(', ', $favoritePlaceholders) . ') THEN 0 ELSE 1 END, agg.count DESC';
            }
        } else {
            $query .= 'ORDER BY agg.count DESC';
        }

        if ($limit > 0) {
            $query .= ' LIMIT ' . (int) $limit;
        }

        return $sql->getArray($query, $params);
    }
}
