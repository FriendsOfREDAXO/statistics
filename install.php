<?php


rex_sql_table::get(rex::getTable('pagestats_data'))
    ->ensureColumn(new rex_sql_column('type', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['type', 'name'])
    ->ensureIndex(new rex_sql_index('type_count', ['type', 'count']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_visits_per_day'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('domain', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['date', 'domain'])
    ->ensureIndex(new rex_sql_index('domain_date', ['domain', 'date']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_visitors_per_day'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('domain', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['date', 'domain'])
    ->ensureIndex(new rex_sql_index('domain_date', ['domain', 'date']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_visits_per_url'))
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(2048)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_visitors_per_url'))
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(2048)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_urlstatus'))
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(2048)'))
    ->ensureColumn(new rex_sql_column('status', 'varchar(255)'))
    ->setPrimaryKey(['hash'])
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_bot'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('category', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('producer', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['name', 'category', 'producer'])
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_hash'))
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('datetime', 'datetime'))
    ->setPrimaryKey(['hash'])
    ->ensureIndex(new rex_sql_index('datetime', ['datetime']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_referer'))
    ->removeColumn('id')
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('referer', 'varchar(2048)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_sessionstats'))
    ->ensureColumn(new rex_sql_column('token', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('lastpage', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('lastvisit', 'datetime'))
    ->ensureColumn(new rex_sql_column('visitduration', 'int'))
    ->ensureColumn(new rex_sql_column('pagecount', 'int'))
    ->setPrimaryKey(['token'])
    ->ensure();

// media
rex_sql_table::get(rex::getTable('pagestats_media'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['url', 'date'])
    ->ensureIndex(new rex_sql_index('date_url', ['date', 'url']))
    ->ensure();


// api
rex_sql_table::get(rex::getTable('pagestats_api'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['name', 'date'])
    ->ensureIndex(new rex_sql_index('date_name', ['date', 'name']))
    ->ensure();

$sql = rex_sql::factory();

// Long utf8mb4 URL/referer columns exceed MySQL's index length limit.
// rex_sql_index cannot express prefix lengths, so these indexes are added manually.
$ensurePrefixIndex = static function (string $table, string $indexName, string $indexSql) use ($sql): void {
    $result = $sql->getArray(
        'SHOW INDEX FROM ' . $sql->escapeIdentifier($table) . ' WHERE Key_name = :index_name',
        ['index_name' => $indexName]
    );

    if ([] === $result) {
        $sql->setQuery(
            'ALTER TABLE ' . $sql->escapeIdentifier($table) . ' ADD INDEX ' . $sql->escapeIdentifier($indexName) . ' (' . $indexSql . ')'
        );
    }
};

$ensurePrefixIndex(rex::getTable('pagestats_visits_per_url'), 'date_url', '`date`, `url`(191)');
$ensurePrefixIndex(rex::getTable('pagestats_visits_per_url'), 'url_date', '`url`(191), `date`');
$ensurePrefixIndex(rex::getTable('pagestats_visitors_per_url'), 'date_url', '`date`, `url`(191)');
$ensurePrefixIndex(rex::getTable('pagestats_visitors_per_url'), 'url_date', '`url`(191), `date`');
$ensurePrefixIndex(rex::getTable('pagestats_urlstatus'), 'url', '`url`(191)');
$ensurePrefixIndex(rex::getTable('pagestats_urlstatus'), 'url_status', '`url`(191), `status`');
$ensurePrefixIndex(rex::getTable('pagestats_referer'), 'date_referer', '`date`, `referer`(191)');
$ensurePrefixIndex(rex::getTable('pagestats_referer'), 'referer_date', '`referer`(191), `date`');

// ip 2 geo database installation
$today = new DateTimeImmutable();
$dbUrl = "https://download.db-ip.com/free/dbip-country-lite-{$today->format('Y-m')}.mmdb.gz";

try {
    $socket = rex_socket::factoryUrl($dbUrl);

    $response = $socket->doGet();
    if ($response->isOk()) {
        $body = $response->getBody();
        $decodedBody = gzdecode($body);
        if (false !== $decodedBody) {
            rex_file::put(rex_path::addonData("statistics", "ip2geo.mmdb"), $decodedBody);
            return true;
        }
    }

    return false;
} catch (rex_socket_exception $e) {
    rex_logger::logException($e);
    return false;
}
