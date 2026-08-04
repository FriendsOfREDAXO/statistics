<?php

$previousRuntimePause = (bool) rex_config::get('statistics', 'statistics_pause_tracking_runtime', false);
rex_config::set('statistics', 'statistics_pause_tracking_runtime', true);

try {

rex_sql_table::get(rex::getTable('pagestats_dump'))->drop();
rex_sql_table::get(rex::getTable('pagestats_data'))->drop();
rex_sql_table::get(rex::getTable('pagestats_visits_per_day'))->drop();
rex_sql_table::get(rex::getTable('pagestats_visitors_per_day'))->drop();
rex_sql_table::get(rex::getTable('pagestats_visits_per_url'))->drop();
rex_sql_table::get(rex::getTable('pagestats_bot'))->drop();
rex_sql_table::get(rex::getTable('pagestats_hash'))->drop();
rex_sql_table::get(rex::getTable('pagestats_referer'))->drop();
rex_sql_table::get(rex::getTable('pagestats_sessionstats'))->drop();

// media
rex_sql_table::get(rex::getTable('pagestats_media'))->drop();

// api
rex_sql_table::get(rex::getTable('pagestats_api'))->drop();

} finally {
    rex_config::set('statistics', 'statistics_pause_tracking_runtime', $previousRuntimePause);
}
