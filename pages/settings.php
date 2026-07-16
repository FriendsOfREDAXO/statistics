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
            $hasMore = $hasMore || (bool) $result['has_more'];

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
            echo rex_view::success("Geo Datenbank konnte nicht aktualisiert werden.");
        }
    }
}


$form = rex_config_form::factory("statistics");

$form->addFieldset("Allgemein");

$field2 = $form->addTextField('statistics_visit_duration');
$field2->setLabel($addon->i18n('statistics_visit_duration'));
$field2->setNotice($addon->i18n('statistics_duration_note'));
$field2->getValidator()->add('type', $addon->i18n('statistics_duration_validate'), 'int');


$field = $form->addTextAreaField('statistics_ignored_paths');
$field->setLabel($addon->i18n('statistics_ignore_paths'));
$field->setNotice($addon->i18n('statistics_paths_note'));

$field = $form->addTextAreaField('statistics_ignored_path_contains');
$field->setLabel($addon->i18n('statistics_ignore_path_contains'));
$field->setNotice($addon->i18n('statistics_ignore_path_contains_note'));

$field = $form->addTextAreaField('statistics_ignored_path_ends');
$field->setLabel($addon->i18n('statistics_ignore_path_ends'));
$field->setNotice($addon->i18n('statistics_ignore_path_ends_note'));


$field3 = $form->addTextAreaField('statistics_ignored_ips');
$field3->setLabel($addon->i18n('statistics_ignore_ips'));
$field3->setNotice($addon->i18n('statistics_ips_note'));


$field3 = $form->addTextAreaField('pagestats_ignored_regex');
$field3->setLabel($addon->i18n('pagestats_ignored_regex'));
$field3->setNotice($addon->i18n('pagestats_ignored_regex_note'));

$regexExamples = [];
$regexExamples[] = '#/(wp-login\.php|xmlrpc\.php|wp-admin)(?:$|[/?])#i';
$regexExamples[] = '#/(?:drupal|joomla|magento|prestashop|typo3)(?:$|[/?])#i';
$regexExamples[] = '#/\.(?:env|sql|htaccess|ini|log|bak|old)(?:$|\?)#i';
$regexExamples[] = '#/(?:phpmyadmin|pma|adminer)(?:$|[/?])#i';

$form->addRawField(
    rex_view::info(
        '<strong>' . htmlspecialchars($addon->i18n('pagestats_ignored_regex_examples_heading'), ENT_QUOTES) . '</strong><br><pre style="margin-top:8px;white-space:pre-wrap;">'
        . htmlspecialchars(implode(PHP_EOL, $regexExamples), ENT_QUOTES)
        . '</pre>'
    )
);


$field4 = $form->addRadioField('statistics_scroll_pagination');
$field4->setLabel($addon->i18n('statistics_scroll_pagination'));
$field4->addOption($addon->i18n('statistics_scroll_table'), 'table');
$field4->addOption($addon->i18n('statistics_scroll_panel'), 'panel');
$field4->addOption($addon->i18n('statistics_scroll_none'), 'none');


$field5 = $form->addRadioField('statistics_ignore_url_params');
$field5->setLabel($addon->i18n('statistics_statistics_ignore_url_params'));
$field5->addOption($addon->i18n('statistics_yes'), 1);
$field5->addOption($addon->i18n('statistics_no'), 0);
$field5->setNotice($addon->i18n('statistics_statistics_ignore_url_params_note'));


$field6 = $form->addRadioField('statistics_default_datefilter_range');
$field6->setLabel($addon->i18n('statistics_default_datefilter_range'));
$field6->addOption($addon->i18n('statistics_default_datefilter_last7days'), 'last7days');
$field6->addOption($addon->i18n('statistics_default_datefilter_last30days'), 'last30days');
$field6->addOption($addon->i18n('statistics_default_datefilter_thisYear'), 'thisYear');
$field6->addOption($addon->i18n('statistics_default_datefilter_wholeTime'), 'wholeTime');
$field6->setNotice($addon->i18n('statistics_default_datefilter_range_note'));


$field7 = $form->addRadioField('statistics_combine_all_domains');
$field7->setLabel('Fasse alle Domains zusammen');
$field7->addOption($addon->i18n('statistics_yes'), 1);
$field7->addOption($addon->i18n('statistics_no'), 0);
$field7->setNotice('Alle Domains werden zu einer "Gesamt" Anzahl zusammengefasst. Deaktivieren um Statistiken für alle Domains einzeln anzuzeigen.');

$field7b = $form->addTextAreaField('statistics_hidden_domains');
$field7b->setLabel($addon->i18n('statistics_hidden_domains'));
$field7b->setNotice($addon->i18n('statistics_hidden_domains_note'));

$field7 = $form->addRadioField('statistics_show_chart_toolbox');
$field7->setLabel('Zeige Toolbox an den Charts');
$field7->addOption($addon->i18n('statistics_yes'), 1);
$field7->addOption($addon->i18n('statistics_no'), 0);


$field8 = $form->addRadioField('statistics_ignore_backend_loggedin');
$field8->setLabel('Eigene Seitenaufrufe ignorieren');
$field8->addOption($addon->i18n('statistics_yes'), 1);
$field8->addOption($addon->i18n('statistics_no'), 0);
$field8->setNotice('Aktivieren, um Seitenaufrufe durch eingeloggte User zu verwerfen.');

$field8b = $form->addRadioField('statistics_pages_visitors_enabled');
$field8b->setLabel($addon->i18n('statistics_pages_visitors_enabled'));
$field8b->addOption($addon->i18n('statistics_yes'), 1);
$field8b->addOption($addon->i18n('statistics_no'), 0);
$field8b->setNotice($addon->i18n('statistics_pages_visitors_enabled_note'));


$field = $form->addRadioField('statistics_rec_onlyok');
$field->setLabel('Nur 200er Aufrufe erfassen');
$field->addOption($addon->i18n('statistics_yes'), 1);
$field->addOption($addon->i18n('statistics_no'), 0);
$field->setNotice('Dadurch werden nur Aufrufe mit einem HTTP Status 200 OK erfasst. Die Statistik "Seitenaufrufe" loggt trotzdem auch Aufrufe ungleich 200.');


// media
$form->addFieldset("Media");

$fm1 = $form->addRadioField('statistics_media_log_all');
$fm1->setLabel($addon->i18n('statistics_media_log_all'));
$fm1->addOption($addon->i18n('statistics_media_yes'), 1);
$fm1->addOption($addon->i18n('statistics_media_no'), 0);
$fm1->setNotice($addon->i18n('statistics_media_log_all_note'));

$fm2 = $form->addRadioField('statistics_media_log_mm');
$fm2->setLabel($addon->i18n('statistics_media_log_mm'));
$fm2->addOption($addon->i18n('statistics_media_yes'), 1);
$fm2->addOption($addon->i18n('statistics_media_no'), 0);
$fm2->setNotice($addon->i18n('statistics_media_log_mm_note'));

$note = rex_view::warning("Nur eine dieser beiden Optionen aktivieren, sonst werden Aufrufe doppelt gezählt.");
$form->addRawField($note);


// api
$form->addFieldset("API");

$field = $form->addRadioField('statistics_api_enable');
$field->setLabel($addon->i18n('statistics_api_enable_campaigns'));
$field->addOption($addon->i18n('statistics_api_yes'), 1);
$field->addOption($addon->i18n('statistics_api_no'), 0);
$field->setNotice($addon->i18n('statistics_api_enable_campaigns_note'));


// parse fragment with setting form
$addon = rex_addon::get('statistics');
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('statistics_settings'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');



// ip2geo section
$geoIpHtml = '
<p>Geo-Datenbank updaten mit der IP-Adressen zu Ländern zugeordnet werden.</p>
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
    foreach (array_values($maintenanceTables) as $index => $tableName) {
        $key = ':t' . $index;
        $placeholders[] = $key;
        $params[$key] = $tableName;
    }

    if ([] !== $placeholders) {
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

$content = $storageUsageHtml . '
<div style="display: flex; flex-wrap: wrap">

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_hash">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_hashes') . '">' . $addon->i18n('statistics_delete_hashes') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_dump">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_dump') . '">' . $addon->i18n('statistics_delete_visits') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_bot">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_bots') . '">' . $addon->i18n('statistics_delete_bots') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_referer">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_referer') . '">' . $addon->i18n('statistics_delete_referer') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_media">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_media_delete_media_confirm') . '">' . $addon->i18n('statistics_media_delete_media') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_campaigns">
<button class="btn btn-danger" type="submit" data-confirm="' . $addon->i18n('statistics_api_delete_api_confirm') . '">' . $addon->i18n('statistics_api_delete_api') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_noise">
<button class="btn btn-warning" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_noise') . '">' . $addon->i18n('statistics_delete_noise') . '</button>
</form>

<form style="margin:5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_old">
<label for="statistics-keep-days" style="margin:0;">' . $addon->i18n('statistics_cleanup_keep_days') . '</label>
<input id="statistics-keep-days" class="form-control" style="width:110px" type="number" min="1" step="1" name="keep_days" value="365">
<button class="btn btn-warning" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_old') . '">' . $addon->i18n('statistics_delete_old') . '</button>
</form>

<form style="margin:5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="delete_raw_old">
<label for="statistics-keep-days-raw" style="margin:0;">' . $addon->i18n('statistics_cleanup_keep_days_raw') . '</label>
<input id="statistics-keep-days-raw" class="form-control" style="width:110px" type="number" min="1" step="1" name="keep_days_raw" value="120">
<button class="btn btn-warning" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_delete_raw_old') . '">' . $addon->i18n('statistics_delete_raw_old') . '</button>
</form>

<form style="margin:5px" action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="func" value="optimize_tables">
<button class="btn btn-primary" type="submit" data-confirm="' . $addon->i18n('statistics_confirm_optimize_tables') . '">' . $addon->i18n('statistics_optimize_tables') . '</button>
</form>

</div>
';


$fragment = new rex_fragment();
$fragment->setVar('class', 'danger', false);
$fragment->setVar('title', $addon->i18n('statistics_maintenance'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
