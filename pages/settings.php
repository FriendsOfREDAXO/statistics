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
        '%/phpmyadmin%',
        '%/pma%',
        '%/.git/%',
        '%/vendor/phpunit%',
        '%apple-touch-icon.png%',
        '%apple-touch-icon-precomposed.png%',
        '%/.well-known/security.txt%',
        '%/.env%',
        '%/.htaccess%',
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
            $parts[] = 'LOWER(' . $column . ') LIKE ' . $paramKey;
            $params[$paramKey] = strtolower((string) $pattern);
        }

        return [implode(' OR ', $parts), $params];
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
        $count = 0;

        [$whereUrl, $paramsUrl] = $buildLikeWhere('url', $noiseLikePatterns);
        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_visits_per_url') . ' WHERE ' . $whereUrl, $paramsUrl);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_urlstatus') . ' WHERE ' . $whereUrl, $paramsUrl);
        $count += $sql->getRows();

        [$whereLastpage, $paramsLastpage] = $buildLikeWhere('lastpage', $noiseLikePatterns);
        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_sessionstats') . ' WHERE ' . $whereLastpage, $paramsLastpage);
        $count += $sql->getRows();

        echo rex_view::success(sprintf($addon->i18n('statistics_deleted_noise'), (string) $count));
    } elseif ($function == 'delete_old') {
        $keepDays = rex_post('keep_days', 'int', 365);
        if ($keepDays < 1) {
            $keepDays = 1;
        }

        $cutoffDate = (new DateTimeImmutable('today'))->modify('-' . $keepDays . ' days')->format('Y-m-d');
        $cutoffDatetime = $cutoffDate . ' 00:00:00';

        $count = 0;

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_visits_per_day') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_visitors_per_day') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_visits_per_url') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_referer') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_media') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_api') . ' WHERE date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_hash') . ' WHERE datetime < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);
        $count += $sql->getRows();

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('pagestats_sessionstats') . ' WHERE lastvisit < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);
        $count += $sql->getRows();

        // Remove stale URL status records when no matching URL stats remain.
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE us FROM ' . rex::getTable('pagestats_urlstatus') . ' us '
            . 'LEFT JOIN ' . rex::getTable('pagestats_visits_per_url') . ' v ON v.url = us.url '
            . 'WHERE v.url IS NULL'
        );
        $count += $sql->getRows();

        echo rex_view::success(sprintf($addon->i18n('statistics_deleted_old'), (string) $count, (string) $keepDays));
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

$field7 = $form->addRadioField('statistics_show_chart_toolbox');
$field7->setLabel('Zeige Toolbox an den Charts');
$field7->addOption($addon->i18n('statistics_yes'), 1);
$field7->addOption($addon->i18n('statistics_no'), 0);


$field8 = $form->addRadioField('statistics_ignore_backend_loggedin');
$field8->setLabel('Eigene Seitenaufrufe ignorieren');
$field8->addOption($addon->i18n('statistics_yes'), 1);
$field8->addOption($addon->i18n('statistics_no'), 0);
$field8->setNotice('Aktivieren, um Seitenaufrufe durch eingeloggte User zu verwerfen.');


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
$content = '
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

</div>
';


$fragment = new rex_fragment();
$fragment->setVar('class', 'danger', false);
$fragment->setVar('title', $addon->i18n('statistics_delete_statistics'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
