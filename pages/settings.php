<?php

use AndiLeni\Statistics\Ip2Geo;

$addon = rex_addon::get('statistics');


// post request which handles deletion of stats data
if (rex_request_method() == 'post') {
    $function = rex_post('func', 'string', '');
    $noiseLikePatterns = [
        '%/wp-login.php%',
        '%/wp-json%',
        '%/wp-config%',
        '%/wp-admin%',
        '%/wp-includes/%',
        '%/wp-content/%',
        '%/xmlrpc.php%',
        '%/wlwmanifest.xml%',
        '%/drupal%',
        '%/joomla%',
        '%/magento%',
        '%/prestashop%',
        '%/typo3%',
        '%/shopware%',
        '%/administrator%',
        '%/admin/login%',
        '%/admin/%',
        '%/api/%',
        '%/api',
        '%/adminer%',
        '%/adminer.php%',
        '%/phpmyadmin%',
        '%/phpmyadmin2%',
        '%/pma%',
        '%/dbadmin%',
        '%/myadmin%',
        '%/webadmin%',
        '%/mysql%',
        '%/phpinfo.php%',
        '%/server-status%',
        '%/server-info%',
        '%/cgi-bin/%',
        '%/webmail%',
        '%/roundcube%',
        '%/.git/%',
        '%/vendor/phpunit%',
        '%apple-touch%',
        '%/.well-known/security.txt%',
        '%/.env%',
        '%/.htaccess%',
        '%.php%',
        '%.json%',
        '%.xml%',
        '%.yml%',
        '%.save%',
        '%.ini%',
        '%.log%',
        '%.bak%',
        '%.old%',
        '%.sql%',
    ];

    $addConfigPatterns = static function (array &$patterns, string $configValue, string $mode): void {
        $lines = explode("\n", str_replace("\r", "", $configValue));
        foreach ($lines as $line) {
            $rule = strtolower(trim((string) $line));
            if ('' === $rule) {
                continue;
            }

            if ('ends' === $mode) {
                $patterns[] = '%' . $rule;
                continue;
            }

            $patterns[] = '%' . $rule . '%';
        }
    };

    $addConfigPatterns($noiseLikePatterns, (string) $addon->getConfig('statistics_ignored_paths', ''), 'contains');
    $addConfigPatterns($noiseLikePatterns, (string) $addon->getConfig('statistics_ignored_path_contains', ''), 'contains');
    $addConfigPatterns($noiseLikePatterns, (string) $addon->getConfig('statistics_ignored_path_ends', ''), 'ends');
    $noiseLikePatterns = array_values(array_unique($noiseLikePatterns));

    $buildLikeWhere = static function (string $column, array $patterns): array {
        $parts = [];
        $params = [];

        foreach (array_values($patterns) as $index => $pattern) {
            $paramKey = ':pattern' . $index;
            $parts[] = $column . ' LIKE ' . $paramKey;
            $params[$paramKey] = (string) $pattern;
        }

        return [implode(' OR ', $parts), $params];
    };

    $runDeleteWithRetry = static function (string $query, array $params = []): int {
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; ++$attempt) {
            try {
                $sql = rex_sql::factory();
                $sql->setQuery($query, $params);

                return (int) $sql->getRows();
            } catch (rex_sql_exception $exception) {
                $message = $exception->getMessage();
                $isLockTimeout = false !== strpos($message, '1205') || false !== strpos(strtolower($message), 'lock wait timeout');

                if (!$isLockTimeout || $attempt >= $maxRetries) {
                    throw $exception;
                }

                usleep(250000);
            }
        }

        return 0;
    };

    $deleteChunked = static function (string $table, string $condition, array $params = [], int $chunkSize = 5000) use ($runDeleteWithRetry): int {
        $total = 0;

        do {
            $affected = $runDeleteWithRetry(
                'DELETE FROM ' . $table . ' WHERE ' . $condition . ' LIMIT ' . (int) $chunkSize,
                $params
            );
            $total += $affected;
        } while ($affected >= $chunkSize);

        return $total;
    };

    $deleteChunkedLimited = static function (string $table, string $condition, array $params = [], int $chunkSize = 5000, int $maxRounds = 20) use ($runDeleteWithRetry): array {
        $total = 0;
        $round = 0;
        $hasMore = false;

        do {
            ++$round;
            $affected = $runDeleteWithRetry(
                'DELETE FROM ' . $table . ' WHERE ' . $condition . ' LIMIT ' . (int) $chunkSize,
                $params
            );
            $total += $affected;

            $hasMore = $affected >= $chunkSize;
        } while ($hasMore && $round < $maxRounds);

        return [
            'deleted' => $total,
            'has_more' => $hasMore,
        ];
    };

    $deleteOrphanUrlStatusChunked = static function (int $chunkSize = 5000) use ($runDeleteWithRetry): int {
        $total = 0;

        do {
            $affected = $runDeleteWithRetry(
                'DELETE FROM ' . rex::getTable('pagestats_urlstatus')
                . ' WHERE url IN ('
                . 'SELECT stale.url FROM ('
                . 'SELECT us.url FROM ' . rex::getTable('pagestats_urlstatus') . ' us '
                . 'LEFT JOIN ' . rex::getTable('pagestats_visits_per_url') . ' v ON v.url = us.url '
                . 'WHERE v.url IS NULL '
                . 'LIMIT ' . (int) $chunkSize
                . ') stale'
                . ')'
            );
            $total += $affected;
        } while ($affected >= $chunkSize);

        return $total;
    };

    if ($function == 'delete_hash') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_hash'));
        echo rex_view::success($sql->getRows() . ' ' . $addon->i18n('statistics_deleted_hashes'));
    } elseif ($function == 'delete_dump') {
        $sql = rex_sql::factory();
        $count = 0;

        $sql->setQuery('delete from ' . rex::getTable('pagestats_data'));
        $count += $sql->getRows();

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visits_per_day'));
        $count += $sql->getRows();

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visitors_per_day'));
        $count += $sql->getRows();

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visits_per_url'));
        $count += $sql->getRows();

        $sql->setQuery('delete from ' . rex::getTable('pagestats_visitors_per_url'));
        $count += $sql->getRows();

        echo rex_view::success($count . ' ' . $addon->i18n('statistics_deleted_dump'));
    } elseif ($function == 'delete_media') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_media'));
        echo rex_view::success($sql->getRows() . ' ' . $addon->i18n('statistics_deleted_bots'));
    } elseif ($function == 'delete_bot') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_bot'));
        echo rex_view::success($sql->getRows() . ' ' . $addon->i18n('statistics_deleted_referer'));
    } elseif ($function == 'delete_referer') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_referer'));
        echo rex_view::success($sql->getRows() . ' ' . $addon->i18n('statistics_deleted_media'));
    } elseif ($function == 'delete_media') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_media'));
        echo rex_view::success('Es wurden ' . $sql->getRows() . ' Einträge aus der Tabelle media gelöscht.</div>');
    } elseif ($function == 'delete_campaigns') {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . rex::getTable('pagestats_api'));
        echo rex_view::success('Es wurden ' . $sql->getRows() . ' Einträge aus der Tabelle api gelöscht.');
    } elseif ($function == 'delete_noise') {
        try {
            $count = 0;
            $hasMore = false;
            $maxRoundsPerRun = 15;
            $chunkSize = 4000;

            [$whereUrl, $paramsUrl] = $buildLikeWhere('url', $noiseLikePatterns);
            $result = $deleteChunkedLimited(rex::getTable('pagestats_visits_per_url'), $whereUrl, $paramsUrl, $chunkSize, $maxRoundsPerRun);
            $count += (int) $result['deleted'];
            $hasMore = (bool) $result['has_more'];

            $result = $deleteChunkedLimited(rex::getTable('pagestats_visitors_per_url'), $whereUrl, $paramsUrl, $chunkSize, $maxRoundsPerRun);
            $count += (int) $result['deleted'];
            $hasMore = $hasMore || (bool) $result['has_more'];

            $result = $deleteChunkedLimited(rex::getTable('pagestats_urlstatus'), $whereUrl, $paramsUrl, $chunkSize, $maxRoundsPerRun);
            $count += (int) $result['deleted'];
            $hasMore = $hasMore || (bool) $result['has_more'];

            [$whereLastpage, $paramsLastpage] = $buildLikeWhere('lastpage', $noiseLikePatterns);
            $result = $deleteChunkedLimited(rex::getTable('pagestats_sessionstats'), $whereLastpage, $paramsLastpage, $chunkSize, $maxRoundsPerRun);
            $count += (int) $result['deleted'];
            $hasMore = $hasMore || (bool) $result['has_more'];

            echo rex_view::success(sprintf($addon->i18n('statistics_deleted_noise'), (string) $count));
            if ($hasMore) {
                echo rex_view::warning(sprintf($addon->i18n('statistics_deleted_noise_partial'), (string) $count));
            }
        } catch (rex_sql_exception $exception) {
            $message = strtolower($exception->getMessage());
            $isLockTimeout = false !== strpos($message, '1205') || false !== strpos($message, 'lock wait timeout');

            if ($isLockTimeout) {
                echo rex_view::error($addon->i18n('statistics_cleanup_lock_timeout'));
            } else {
                rex_logger::logException($exception);
                echo rex_view::error($exception->getMessage());
            }
        }
    } elseif ($function == 'delete_old') {
        try {
            $keepDays = rex_post('keep_days', 'int', 365);
            if ($keepDays < 1) {
                $keepDays = 1;
            }

            $cutoffDate = (new DateTimeImmutable('today'))->modify('-' . $keepDays . ' days')->format('Y-m-d');
            $cutoffDatetime = $cutoffDate . ' 00:00:00';

            $count = 0;
            $count += $deleteChunked(rex::getTable('pagestats_visits_per_day'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_visitors_per_day'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_visits_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_visitors_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_referer'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_media'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_api'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_hash'), 'datetime < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);
            $count += $deleteChunked(rex::getTable('pagestats_sessionstats'), 'lastvisit < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);

            // Remove stale URL status records when no matching URL stats remain.
            $count += $deleteOrphanUrlStatusChunked();

            echo rex_view::success(sprintf($addon->i18n('statistics_deleted_old'), (string) $count, (string) $keepDays));
        } catch (rex_sql_exception $exception) {
            $message = strtolower($exception->getMessage());
            $isLockTimeout = false !== strpos($message, '1205') || false !== strpos($message, 'lock wait timeout');

            if ($isLockTimeout) {
                echo rex_view::error($addon->i18n('statistics_cleanup_lock_timeout'));
            } else {
                rex_logger::logException($exception);
                echo rex_view::error($exception->getMessage());
            }
        }
    } elseif ($function == 'delete_raw_old') {
        try {
            $keepDaysRaw = rex_post('keep_days_raw', 'int', 120);
            if ($keepDaysRaw < 1) {
                $keepDaysRaw = 1;
            }

            $cutoffDate = (new DateTimeImmutable('today'))->modify('-' . $keepDaysRaw . ' days')->format('Y-m-d');
            $cutoffDatetime = $cutoffDate . ' 00:00:00';

            $count = 0;

            // Reduce high-cardinality raw tables first.
            $count += $deleteChunked(rex::getTable('pagestats_visits_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_visitors_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_referer'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_media'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_api'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $count += $deleteChunked(rex::getTable('pagestats_sessionstats'), 'lastvisit < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);
            $count += $deleteChunked(rex::getTable('pagestats_hash'), 'datetime < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);

            // Remove stale URL status records without matching URL rows.
            $count += $deleteOrphanUrlStatusChunked();

            echo rex_view::success(sprintf($addon->i18n('statistics_deleted_raw_old'), (string) $count, (string) $keepDaysRaw));
        } catch (rex_sql_exception $exception) {
            $message = strtolower($exception->getMessage());
            $isLockTimeout = false !== strpos($message, '1205') || false !== strpos($message, 'lock wait timeout');

            if ($isLockTimeout) {
                echo rex_view::error($addon->i18n('statistics_cleanup_lock_timeout'));
            } else {
                rex_logger::logException($exception);
                echo rex_view::error($exception->getMessage());
            }
        }
    } elseif ($function == 'optimize_tables') {
        $optimized = 0;

        $tablesToOptimize = [
            rex::getTable('pagestats_hash'),
            rex::getTable('pagestats_data'),
            rex::getTable('pagestats_visits_per_day'),
            rex::getTable('pagestats_visitors_per_day'),
            rex::getTable('pagestats_visits_per_url'),
            rex::getTable('pagestats_visitors_per_url'),
            rex::getTable('pagestats_urlstatus'),
            rex::getTable('pagestats_bot'),
            rex::getTable('pagestats_referer'),
            rex::getTable('pagestats_media'),
            rex::getTable('pagestats_api'),
            rex::getTable('pagestats_sessionstats'),
        ];

        try {
            foreach ($tablesToOptimize as $tableName) {
                $sql = rex_sql::factory();
                $sql->setQuery('OPTIMIZE TABLE ' . $tableName);
                ++$optimized;
            }

            echo rex_view::success(sprintf($addon->i18n('statistics_optimized_tables'), (string) $optimized));
        } catch (rex_sql_exception $exception) {
            $message = strtolower($exception->getMessage());
            $isLockTimeout = false !== strpos($message, '1205') || false !== strpos($message, 'lock wait timeout');

            if ($isLockTimeout) {
                echo rex_view::error($addon->i18n('statistics_cleanup_lock_timeout'));
            } else {
                rex_logger::logException($exception);
                echo rex_view::error($exception->getMessage());
            }
        }
    } elseif ($function == 'updateGeo2Ip') {
        $updated = Ip2Geo::updateDatabase();
        if ($updated) {
            echo rex_view::success("Geo Datenbank geupdated.");
        } else {
            echo rex_view::error("Geo Datenbank konnte nicht aktualisiert werden.");
        }
    }
}


$renderConfigPanel = static function (string $panelKey, string $title, string $formBody): void {
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', $formBody, false);

    echo '<div id="statistics-settings-panel-' . htmlspecialchars($panelKey, ENT_QUOTES) . '">';
    echo $fragment->parse('core/page/section.php');
    echo '</div>';
};

$regexExamples = [];
$regexExamples[] = '#/(wp-login\.php|xmlrpc\.php|wp-admin)(?:$|[/?])#i';
$regexExamples[] = '#/(?:drupal|joomla|magento|prestashop|typo3)(?:$|[/?])#i';
$regexExamples[] = '#/\.(?:env|sql|htaccess|ini|log|bak|old)(?:$|\?)#i';
$regexExamples[] = '#/(?:phpmyadmin|pma|adminer)(?:$|[/?])#i';

// Tracking / Erkennung
$trackingForm = rex_config_form::factory('statistics', 'tracking');
$field = $trackingForm->addTextField('statistics_visit_duration');
$field->setLabel($addon->i18n('statistics_visit_duration'));
$field->setNotice($addon->i18n('statistics_duration_note'));
$field->getValidator()->add('type', $addon->i18n('statistics_duration_validate'), 'int');

$field = $trackingForm->addRadioField('statistics_identity_mode');
$field->setLabel($addon->i18n('statistics_identity_mode'));
$field->addOption($addon->i18n('statistics_identity_mode_stateless'), 'stateless');
$field->addOption($addon->i18n('statistics_identity_mode_session'), 'session');
$field->setNotice($addon->i18n('statistics_identity_mode_note'));

$identityHintHtml = '<strong>' . htmlspecialchars($addon->i18n('statistics_identity_mode_hint_title'), ENT_QUOTES) . '</strong><br>'
    . htmlspecialchars($addon->i18n('statistics_identity_mode_hint_stateless'), ENT_QUOTES) . '<br>'
    . htmlspecialchars($addon->i18n('statistics_identity_mode_hint_session'), ENT_QUOTES) . '<br>'
    . htmlspecialchars($addon->i18n('statistics_identity_mode_hint_lock'), ENT_QUOTES);
$trackingForm->addRawField(rex_view::info($identityHintHtml));

$field = $trackingForm->addTextField('statistics_token_rotation_hours');
$field->setLabel($addon->i18n('statistics_token_rotation_hours'));
$field->setNotice($addon->i18n('statistics_token_rotation_hours_note'));
$field->getValidator()->add('type', $addon->i18n('statistics_token_rotation_hours_validate'), 'int');

$field = $trackingForm->addRadioField('statistics_ignore_backend_loggedin');
$field->setLabel('Eigene Seitenaufrufe ignorieren');
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice('Aktivieren, um Seitenaufrufe durch eingeloggte User zu verwerfen.');

$field = $trackingForm->addRadioField('statistics_pages_visitors_enabled');
$field->setLabel($addon->i18n('statistics_pages_visitors_enabled'));
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice($addon->i18n('statistics_pages_visitors_enabled_note'));

$field = $trackingForm->addRadioField('statistics_rec_onlyok');
$field->setLabel('Nur 200er Aufrufe erfassen');
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice('Dadurch werden nur Aufrufe mit einem HTTP Status 200 OK erfasst. Die Statistik "Seitenaufrufe" loggt trotzdem auch Aufrufe ungleich 200.');

$renderConfigPanel('tracking', 'Tracking & Erkennung', $trackingForm->get());

// Filter / Erfassung
$filterForm = rex_config_form::factory('statistics', 'filter');

$field = $filterForm->addTextAreaField('statistics_ignored_paths');
$field->setLabel($addon->i18n('statistics_ignore_paths'));
$field->setNotice($addon->i18n('statistics_paths_note'));

$field = $filterForm->addTextAreaField('statistics_ignored_path_contains');
$field->setLabel($addon->i18n('statistics_ignore_path_contains'));
$field->setNotice($addon->i18n('statistics_ignore_path_contains_note'));

$field = $filterForm->addTextAreaField('statistics_ignored_path_ends');
$field->setLabel($addon->i18n('statistics_ignore_path_ends'));
$field->setNotice($addon->i18n('statistics_ignore_path_ends_note'));

$field = $filterForm->addTextAreaField('statistics_ignored_ips');
$field->setLabel($addon->i18n('statistics_ignore_ips'));
$field->setNotice($addon->i18n('statistics_ips_note'));

$field = $filterForm->addTextAreaField('pagestats_ignored_regex');
$field->setLabel($addon->i18n('pagestats_ignored_regex'));
$field->setNotice($addon->i18n('pagestats_ignored_regex_note'));

$filterForm->addRawField(
    rex_view::info(
        '<strong>' . htmlspecialchars($addon->i18n('pagestats_ignored_regex_examples_heading'), ENT_QUOTES) . '</strong><br><pre style="margin-top:8px;white-space:pre-wrap;">'
        . htmlspecialchars(implode(PHP_EOL, $regexExamples), ENT_QUOTES)
        . '</pre>'
    )
);

$field = $filterForm->addRadioField('statistics_ignore_url_params');
$field->setLabel($addon->i18n('statistics_statistics_ignore_url_params'));
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice($addon->i18n('statistics_statistics_ignore_url_params_note'));

$field = $filterForm->addRadioField('statistics_default_datefilter_range');
$field->setLabel($addon->i18n('statistics_default_datefilter_range'));
$field->addOption($addon->i18n('statistics_default_datefilter_last7days'), 'last7days');
$field->addOption($addon->i18n('statistics_default_datefilter_last30days'), 'last30days');
$field->addOption($addon->i18n('statistics_default_datefilter_thisYear'), 'thisYear');
$field->addOption($addon->i18n('statistics_default_datefilter_wholeTime'), 'wholeTime');
$field->setNotice($addon->i18n('statistics_default_datefilter_range_note'));

$field = $filterForm->addRadioField('statistics_combine_all_domains');
$field->setLabel('Fasse alle Domains zusammen');
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice('Alle Domains werden zu einer "Gesamt" Anzahl zusammengefasst. Deaktivieren um Statistiken für alle Domains einzeln anzuzeigen.');

$field = $filterForm->addTextAreaField('statistics_hidden_domains');
$field->setLabel($addon->i18n('statistics_hidden_domains'));
$field->setNotice($addon->i18n('statistics_hidden_domains_note'));

$renderConfigPanel('filter', 'Filter & Erfassung', $filterForm->get());

// Darstellung
$displayForm = rex_config_form::factory('statistics', 'display');

$field = $displayForm->addRadioField('statistics_scroll_pagination');
$field->setLabel($addon->i18n('statistics_scroll_pagination'));
$field->addOption($addon->i18n('statistics_scroll_table'), 'table');
$field->addOption($addon->i18n('statistics_scroll_panel'), 'panel');
$field->addOption($addon->i18n('statistics_scroll_none'), 'none');

$field = $displayForm->addRadioField('statistics_show_chart_toolbox');
$field->setLabel('Zeige Toolbox an den Charts');
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);

$renderConfigPanel('display', 'Darstellung & UX', $displayForm->get());

// Media
$mediaForm = rex_config_form::factory('statistics', 'media');
$field = $mediaForm->addRadioField('statistics_media_log_all');
$field->setLabel($addon->i18n('statistics_media_log_all'));
$field->addOption($addon->i18n('statistics_media_yes'), 1);
$field->addOption($addon->i18n('statistics_media_no'), 0);
$field->setNotice($addon->i18n('statistics_media_log_all_note'));

$field = $mediaForm->addRadioField('statistics_media_log_mm');
$field->setLabel($addon->i18n('statistics_media_log_mm'));
$field->addOption($addon->i18n('statistics_media_yes'), 1);
$field->addOption($addon->i18n('statistics_media_no'), 0);
$field->setNotice($addon->i18n('statistics_media_log_mm_note'));

$mediaForm->addRawField(rex_view::warning('Nur eine dieser beiden Optionen aktivieren, sonst werden Aufrufe doppelt gezählt.'));
$renderConfigPanel('media', 'Media', $mediaForm->get());

// API
$apiForm = rex_config_form::factory('statistics', 'api');
$field = $apiForm->addRadioField('statistics_api_enable');
$field->setLabel($addon->i18n('statistics_api_enable_campaigns'));
$field->addOption($addon->i18n('statistics_api_yes'), 1);
$field->addOption($addon->i18n('statistics_api_no'), 0);
$field->setNotice($addon->i18n('statistics_api_enable_campaigns_note'));

$renderConfigPanel('api', 'API', $apiForm->get());



// ip2geo section
$geoDbPath = rex_path::addonData('statistics', 'ip2geo.mmdb');
$geoDbAvailable = is_file($geoDbPath) && filesize($geoDbPath) > 0;
$geoDbSize = $geoDbAvailable ? (int) filesize($geoDbPath) : 0;
$geoDbSizeFormatted = rex_formatter::bytes($geoDbSize);
$geoDbLastUpdated = $geoDbAvailable
    ? date('Y-m-d H:i:s', (int) filemtime($geoDbPath))
    : $addon->i18n('statistics_geo_status_not_available');
$geoDbStatusLabel = $geoDbAvailable
    ? $addon->i18n('statistics_geo_status_loaded')
    : $addon->i18n('statistics_geo_status_missing');
$geoDbStatusClass = $geoDbAvailable ? 'alert-success' : 'alert-warning';

$geoIpHtml = '
<p>Geo-Datenbank updaten mit der IP-Adressen zu Ländern zugeordnet werden.</p>
<div class="alert ' . $geoDbStatusClass . '" style="margin:10px 5px;">
<strong>' . htmlspecialchars($addon->i18n('statistics_geo_status'), ENT_QUOTES) . ':</strong> ' . htmlspecialchars($geoDbStatusLabel, ENT_QUOTES) . '<br>
<strong>' . htmlspecialchars($addon->i18n('statistics_geo_last_update'), ENT_QUOTES) . ':</strong> ' . htmlspecialchars($geoDbLastUpdated, ENT_QUOTES) . '<br>
<strong>' . htmlspecialchars($addon->i18n('statistics_geo_file_size'), ENT_QUOTES) . ':</strong> ' . htmlspecialchars($geoDbSizeFormatted, ENT_QUOTES) . '
</div>
<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="updateGeo2Ip">
<button class="btn btn-primary" type="submit">Geo-Datenbank Updaten</button>
</form>
<p><a href="https://db-ip.com">IP Geolocation by DB-IP</a></p>
';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', "IP 2 Geo", false);
$fragment->setVar('body', $geoIpHtml, false);
echo $fragment->parse('core/page/section.php');




// forms which should make a post request to this page to trigger deletion of stats data
$maintenanceTables = [
    rex::getTable('pagestats_hash'),
    rex::getTable('pagestats_data'),
    rex::getTable('pagestats_visits_per_day'),
    rex::getTable('pagestats_visitors_per_day'),
    rex::getTable('pagestats_visits_per_url'),
    rex::getTable('pagestats_visitors_per_url'),
    rex::getTable('pagestats_urlstatus'),
    rex::getTable('pagestats_bot'),
    rex::getTable('pagestats_referer'),
    rex::getTable('pagestats_media'),
    rex::getTable('pagestats_api'),
    rex::getTable('pagestats_sessionstats'),
];

$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $power);

    return number_format($value, $power > 0 ? 2 : 0, ',', '.') . ' ' . $units[$power];
};

$storageUsageByTable = array_fill_keys($maintenanceTables, 0);

try {
    $params = [];
    $placeholders = [];
    foreach ($maintenanceTables as $index => $tableName) {
        $key = ':t' . $index;
        $placeholders[] = $key;
        $params[$key] = $tableName;
    }

    $sql = rex_sql::factory();
    $rows = $sql->getArray(
        'SELECT table_name, IFNULL(data_length, 0) + IFNULL(index_length, 0) AS bytes '
        . 'FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() '
        . 'AND table_name IN (' . implode(', ', $placeholders) . ')',
        $params
    );

    foreach ($rows as $row) {
        $tableName = (string) ($row['table_name'] ?? '');
        if (isset($storageUsageByTable[$tableName])) {
            $storageUsageByTable[$tableName] = (int) ($row['bytes'] ?? 0);
        }
    }
} catch (Throwable $throwable) {
    // Fallback to 0 values if information_schema is not accessible.
}

$totalStorageUsage = array_sum($storageUsageByTable);

$storageUsageHtml = '<div class="alert alert-info" style="margin-bottom:10px;">';
$storageUsageHtml .= '<strong>' . htmlspecialchars($addon->i18n('statistics_storage_usage_current'), ENT_QUOTES) . ':</strong> ';
$storageUsageHtml .= htmlspecialchars($formatBytes($totalStorageUsage), ENT_QUOTES);
$storageUsageHtml .= '<br><small>' . htmlspecialchars($addon->i18n('statistics_storage_usage_note'), ENT_QUOTES) . '</small>';
$storageUsageHtml .= '</div>';

$storageUsageHtml .= '<table class="table table-striped table-bordered" style="margin-bottom:15px;">';
$storageUsageHtml .= '<thead><tr>';
$storageUsageHtml .= '<th>' . htmlspecialchars($addon->i18n('statistics_storage_usage_table'), ENT_QUOTES) . '</th>';
$storageUsageHtml .= '<th>' . htmlspecialchars($addon->i18n('statistics_storage_usage_size'), ENT_QUOTES) . '</th>';
$storageUsageHtml .= '</tr></thead><tbody>';

foreach ($storageUsageByTable as $tableName => $bytes) {
    $storageUsageHtml .= '<tr>';
    $storageUsageHtml .= '<td>' . htmlspecialchars($tableName, ENT_QUOTES) . '</td>';
    $storageUsageHtml .= '<td data-sort="' . htmlspecialchars((string) $bytes, ENT_QUOTES) . '">' . htmlspecialchars($formatBytes((int) $bytes), ENT_QUOTES) . '</td>';
    $storageUsageHtml .= '</tr>';
}

$storageUsageHtml .= '<tr>';
$storageUsageHtml .= '<td><strong>' . htmlspecialchars($addon->i18n('statistics_storage_usage_total'), ENT_QUOTES) . '</strong></td>';
$storageUsageHtml .= '<td data-sort="' . htmlspecialchars((string) $totalStorageUsage, ENT_QUOTES) . '"><strong>' . htmlspecialchars($formatBytes((int) $totalStorageUsage), ENT_QUOTES) . '</strong></td>';
$storageUsageHtml .= '</tr>';
$storageUsageHtml .= '</tbody></table>';

$renderActionCard = static function (string $title, string $scope, string $formHtml): string {
    $html = '<div class="panel panel-default" style="margin-bottom:10px;">';
    $html .= '<div class="panel-body" style="padding:12px;">';
    $html .= '<div style="font-weight:600;margin-bottom:4px;">' . htmlspecialchars($title, ENT_QUOTES) . '</div>';
    $html .= '<div style="font-size:12px;color:#6c7785;margin-bottom:10px;">' . htmlspecialchars($scope, ENT_QUOTES) . '</div>';
    $html .= $formHtml;
    $html .= '</div>';
    $html .= '</div>';

    return $html;
};

$deleteActionsHtml = '';
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_hashes'),
    $addon->i18n('statistics_maintenance_scope_hashes'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_hash">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_hashes') . '">' . $addon->i18n('statistics_delete_hashes') . '</button>'
    . '</form>'
);
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_visits'),
    $addon->i18n('statistics_maintenance_scope_all'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_dump">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_dump') . '">' . $addon->i18n('statistics_delete_visits') . '</button>'
    . '</form>'
);
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_bots'),
    $addon->i18n('statistics_maintenance_scope_bot'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_bot">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_bots') . '">' . $addon->i18n('statistics_delete_bots') . '</button>'
    . '</form>'
);
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_referer'),
    $addon->i18n('statistics_maintenance_scope_referer'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_referer">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_referer') . '">' . $addon->i18n('statistics_delete_referer') . '</button>'
    . '</form>'
);
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_media_delete_media'),
    $addon->i18n('statistics_maintenance_scope_media'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_media">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_media_delete_media_confirm') . '">' . $addon->i18n('statistics_media_delete_media') . '</button>'
    . '</form>'
);
$deleteActionsHtml .= $renderActionCard(
    $addon->i18n('statistics_api_delete_api'),
    $addon->i18n('statistics_maintenance_scope_api'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . '<input type="hidden" name="func" value="delete_campaigns">'
    . '<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_api_delete_api_confirm') . '">' . $addon->i18n('statistics_api_delete_api') . '</button>'
    . '</form>'
);

$maintenanceTasksHtml = '';
$maintenanceTasksHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_noise'),
    $addon->i18n('statistics_maintenance_scope_noise'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post" data-confirm="' . $addon->i18n('statistics_confirm_delete_noise') . '">'
    . '<input type="hidden" name="func" value="delete_noise">'
    . '<button class="btn btn-default" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_noise') . '">' . $addon->i18n('statistics_delete_noise') . '</button>'
    . '</form>'
);
$maintenanceTasksHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_old'),
    $addon->i18n('statistics_maintenance_scope_keep_days'),
    '<form style="display:flex;align-items:center;gap:8px;flex-wrap:wrap" action="' . rex_url::currentBackendPage() . '" method="post" data-confirm="' . $addon->i18n('statistics_confirm_delete_old') . '">'
    . '<input type="hidden" name="func" value="delete_old">'
    . '<label for="statistics-keep-days" style="margin:0;">' . $addon->i18n('statistics_cleanup_keep_days') . '</label>'
    . '<input id="statistics-keep-days" class="form-control" style="width:110px" type="number" min="1" step="1" name="keep_days" value="365">'
    . '<button class="btn btn-default" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_old') . '">' . $addon->i18n('statistics_delete_old') . '</button>'
    . '</form>'
);
$maintenanceTasksHtml .= $renderActionCard(
    $addon->i18n('statistics_delete_raw_old'),
    $addon->i18n('statistics_maintenance_scope_keep_days_raw'),
    '<form style="display:flex;align-items:center;gap:8px;flex-wrap:wrap" action="' . rex_url::currentBackendPage() . '" method="post" data-confirm="' . $addon->i18n('statistics_confirm_delete_raw_old') . '">'
    . '<input type="hidden" name="func" value="delete_raw_old">'
    . '<label for="statistics-keep-days-raw" style="margin:0;">' . $addon->i18n('statistics_cleanup_keep_days_raw') . '</label>'
    . '<input id="statistics-keep-days-raw" class="form-control" style="width:110px" type="number" min="1" step="1" name="keep_days_raw" value="120">'
    . '<button class="btn btn-default" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_raw_old') . '">' . $addon->i18n('statistics_delete_raw_old') . '</button>'
    . '</form>'
);
$maintenanceTasksHtml .= $renderActionCard(
    $addon->i18n('statistics_optimize_tables'),
    $addon->i18n('statistics_maintenance_scope_optimize'),
    '<form action="' . rex_url::currentBackendPage() . '" method="post" data-confirm="' . $addon->i18n('statistics_confirm_optimize_tables') . '">'
    . '<input type="hidden" name="func" value="optimize_tables">'
    . '<button class="btn btn-default" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_optimize_tables') . '">' . $addon->i18n('statistics_optimize_tables') . '</button>'
    . '</form>'
);

$content = $storageUsageHtml;
$content .= '<div class="row">';
$content .= '<div class="col-md-6">';
$content .= '<div class="alert alert-danger" style="margin-bottom:10px;">';
$content .= '<strong>' . htmlspecialchars($addon->i18n('statistics_maintenance_delete_heading'), ENT_QUOTES) . '</strong><br>';
$content .= '<small>' . htmlspecialchars($addon->i18n('statistics_maintenance_delete_note'), ENT_QUOTES) . '</small>';
$content .= '</div>';
$content .= $deleteActionsHtml;
$content .= '</div>';

$content .= '<div class="col-md-6">';
$content .= '<div class="alert alert-info" style="margin-bottom:10px;">';
$content .= '<strong>' . htmlspecialchars($addon->i18n('statistics_maintenance_tasks_heading'), ENT_QUOTES) . '</strong><br>';
$content .= '<small>' . htmlspecialchars($addon->i18n('statistics_maintenance_tasks_note'), ENT_QUOTES) . '</small>';
$content .= '</div>';
$content .= $maintenanceTasksHtml;
$content .= '</div>';
$content .= '</div>';


$fragment = new rex_fragment();
$fragment->setVar('class', 'danger', false);
$fragment->setVar('title', $addon->i18n('statistics_maintenance'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
