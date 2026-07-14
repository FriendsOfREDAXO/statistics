<?php

use AndiLeni\Statistics\MediaRequest;
use AndiLeni\Statistics\Visit;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Jaybizzle\CrawlerDetect\CrawlerDetect;



if (rex::isBackend()) {
    $addon = rex_addon::get('statistics');


    // permissions
    rex_perm::register('statistics[]', null);
    rex_perm::register('statistics[settings]', null, rex_perm::OPTIONS);


    rex_view::addCssFile($addon->getAssetsUrl('datatables.min.css'));
    rex_view::addCssFile($addon->getAssetsUrl('statistics.css'));

    $echartsAddon = rex_addon::get('echarts');
    if ($echartsAddon->isAvailable()) {
        rex_view::addJsFile($echartsAddon->getAssetsUrl('echarts.min.js'));
    } else {
        rex_view::addJsFile($addon->getAssetsUrl('echarts.min.js'));
    }
    rex_view::addJsFile($addon->getAssetsUrl('dark.js'));
    rex_view::addJsFile($addon->getAssetsUrl('shine.js'));
    rex_view::addJsFile($addon->getAssetsUrl('datatables.min.js'));

    rex_view::addJsFile($addon->getAssetsUrl('statistics.js'));

    if (rex_addon::get('cronjob')->isAvailable() && !rex::isSafeMode()) {
        rex_cronjob_manager::registerType('rex_statistics_hashremove_cronjob');
    }

    $pagination_scroll = $addon->getConfig('statistics_scroll_pagination');
    if ($pagination_scroll == 'panel') {
        rex_view::addJsFile($addon->getAssetsUrl('statistics_scroll_container.js'));
    } elseif ($pagination_scroll == 'table') {
        rex_view::addJsFile($addon->getAssetsUrl('statistics_scroll_table.js'));
    }
}


// set variable to check in EP whether the visit is coming from a logged-in user or not
if (rex::isFrontend()) {
    $addon = rex_addon::get('statistics');
    $ignore_backend_loggedin = $addon->getConfig('statistics_ignore_backend_loggedin');

    if ($ignore_backend_loggedin) {
        $statistics_has_backend_login = rex_backend_login::hasSession();
    } else {
        $statistics_has_backend_login = false;
    }

    rex_login::startSession();

    $token = rex_session("statistics_token", "string", null);

    if ($token === null) {
        $bytes = random_bytes(20);
        $token = bin2hex($bytes);

        rex_set_session('statistics_token', $token);
    }
} else {
    $statistics_has_backend_login = true;
    $token = "";
}



// NOTICE: EP 'RESPONSE_SHUTDOWN' is not called on madia request
// do actions after content is delivered
rex_extension::register('RESPONSE_SHUTDOWN', function () use ($statistics_has_backend_login, $token) {

    if (rex::isFrontend()) {

        $addon = rex_addon::get('statistics');
        $log_all = $addon->getConfig('statistics_log_all');
        $ignore_backend_loggedin = $addon->getConfig('statistics_ignore_backend_loggedin');


        // return and do not save when visit is coming from a logged-in user
        if ($ignore_backend_loggedin && $statistics_has_backend_login) {
            return;
        }


        // domain
        try {
            $domain = rex::getRequest()->getHost();
        } catch (SuspiciousOperationException $e) {
            $domain = 'undefined';
        }

        // page url (raw URL including parameters for ignore checks)
        $url = $domain . rex::getRequest()->getRequestUri();

        // request response code
        $response_code = rex_response::getStatus();

        // get ip from visitor, set to 0.0.0.0 when ip can not be determined
        $clientAddress = rex::getRequest()->getClientIp();
        $clientAddress = $clientAddress ? $clientAddress : '0.0.0.0';

        // user agent
        $userAgent = rex_server('HTTP_USER_AGENT', 'string', '');

        $visit = new Visit($clientAddress, $url, $userAgent, $domain, $token, $response_code);


        // Track only frontend requests if page url should not be ignored
        // ignore requests with empty user agent
        if (!rex::isBackend() && $userAgent != '' && !$visit->shouldIgnore()) {

            // visit is not a media request, hence either bot or human visitor

            // parse useragent
            $visit->parseUA();

            if ($visit->isBot()) {

                // visitor is a bot
                $visit->saveBot();
            } else {

                // if matomo was not able to detect a bot try CrawlerDetect
                $CrawlerDetect = new CrawlerDetect;

                if ($CrawlerDetect->isCrawler($userAgent)) {
                    // true if crawler user agent detected

                    $crawlerName = $CrawlerDetect->getMatches() ?? "unknown crawler";
                    $visit->saveCrawlerDetect($crawlerName);
                } else {

                    if ($visit->shouldSaveVisit() && !$visit->DeviceDetector->isLibrary()) {

                        $recordOnlyOk = (bool) $addon->getConfig('statistics_rec_onlyok', false);
                        $shouldRecordResponse = !$recordOnlyOk || $response_code === rex_response::HTTP_OK;

                        if ($shouldRecordResponse) {
                            // optionally ignore url parameters after ignore checks were done on the raw URL
                            if ((bool) $addon->getConfig('statistics_ignore_url_params')) {
                                $visit->setUrl(Visit::removeUrlParameters($visit->getUrl()));
                            }

                            // visitduration, number pages visited, last visited page
                            if (rex::getRequest()->getRequestUri() !== '/favicon.ico') {
                                $sql = rex_sql::factory();
                                $sql->setQuery(
                                    'INSERT INTO ' . rex::getTable('pagestats_sessionstats') . ' (token, lastpage, lastvisit, visitduration, pagecount) VALUES (:token, :lastpage, NOW(), 0, 1) ON DUPLICATE KEY UPDATE lastpage = VALUES(lastpage), visitduration = visitduration + (NOW() - lastvisit), lastvisit = NOW(), pagecount = pagecount + 1',
                                    [':token' => $token, ':lastpage' => $visit->getUrl()]
                                );
                            }

                            $visit->updateVisitsPerUrl();

                            // visitor is human
                            // check hash with save_visit, if true then save visit

                            // check if referer exists, if yes safe it
                            $referer = rex_server('HTTP_REFERER', 'string', '');
                            if ($referer != '') {
                                $referer = urldecode($referer);

                                if (!str_starts_with($referer, rex::getServer())) {
                                    $visit->saveReferer($referer);
                                }
                            }


                            // check if unique visitor
                            if ($visit->shouldSaveVisitor()) {

                                // save visitor
                                $visit->persistVisitor();
                            }
                            $visit->persist();
                        }
                    }
                }
            }
        }
    }
});


// media
if (rex::isBackend()) {

    if (rex_addon::get('media_manager')->isAvailable()) {
        rex_media_manager::addEffect(rex_effect_stats_mm::class);
    }
} else {

    rex_extension::register('MEDIA_MANAGER_AFTER_SEND', function () {
        $addon = rex_addon::get('statistics');

        if ($addon->getConfig('statistics_media_log_all') == true) {

            $url = rex_server('REQUEST_URI', 'string', '');

            $media_request = new MediaRequest($url);

            if ($media_request->isMedia()) {

                $media_request->save();
            }
        }
    });
}
