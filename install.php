<?php

$previousRuntimePause = (bool) rex_config::get('statistics', 'statistics_pause_tracking_runtime', false);
rex_config::set('statistics', 'statistics_pause_tracking_runtime', true);

try {

$sql = rex_sql::factory();

$tableExists = static function (string $table): bool {
    if ('' === $table) {
        return false;
    }

    return rex_sql_table::get($table)->exists();
};

$hasExpectedPrimaryKey = static function (string $table, array $keyColumns) use ($sql, $tableExists): bool {
    if (!$tableExists($table)) {
        return false;
    }

    $rows = $sql->getArray(
        'SHOW INDEX FROM ' . $sql->escapeIdentifier($table) . " WHERE Key_name = 'PRIMARY'"
    );

    if ([] === $rows) {
        return false;
    }

    usort($rows, static function (array $left, array $right): int {
        return (int) ($left['Seq_in_index'] ?? 0) <=> (int) ($right['Seq_in_index'] ?? 0);
    });

    $existing = array_map(static fn(array $row): string => (string) $row['Column_name'], $rows);

    return $existing === $keyColumns;
};

$hasDuplicateKeys = static function (string $table, array $keyColumns) use ($sql, $tableExists): bool {
    if (!$tableExists($table)) {
        return false;
    }

    $quotedKeys = array_map([$sql, 'escapeIdentifier'], $keyColumns);
    $groupBy = implode(', ', $quotedKeys);

    $hasDuplicate = $sql->getValue(
        'SELECT 1 FROM ' . $sql->escapeIdentifier($table)
        . ' GROUP BY ' . $groupBy
        . ' HAVING COUNT(*) > 1'
        . ' LIMIT 1'
    );

    return null !== $hasDuplicate;
};

$deduplicateCountTable = static function (string $table, array $keyColumns, string $countColumn = 'count') use ($sql, $tableExists, $hasExpectedPrimaryKey, $hasDuplicateKeys): void {
    if (!$tableExists($table)) {
        return;
    }

    if ($hasExpectedPrimaryKey($table, $keyColumns)) {
        return;
    }

    if (!$hasDuplicateKeys($table, $keyColumns)) {
        return;
    }

    $quotedKeys = array_map([$sql, 'escapeIdentifier'], $keyColumns);
    $groupBy = implode(', ', $quotedKeys);
    $columns = implode(', ', array_merge($quotedKeys, [$sql->escapeIdentifier($countColumn)]));

    $tempTable = $table . '_dedup_install_tmp';
    $sql->setQuery('DROP TEMPORARY TABLE IF EXISTS ' . $sql->escapeIdentifier($tempTable));
    $sql->setQuery(
        'CREATE TEMPORARY TABLE ' . $sql->escapeIdentifier($tempTable) . ' AS '
        . 'SELECT ' . $groupBy . ', SUM(' . $sql->escapeIdentifier($countColumn) . ') AS ' . $sql->escapeIdentifier($countColumn)
        . ' FROM ' . $sql->escapeIdentifier($table)
        . ' GROUP BY ' . $groupBy
    );
    $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($table));
    $sql->setQuery(
        'INSERT INTO ' . $sql->escapeIdentifier($table)
        . ' (' . $columns . ') '
        . 'SELECT ' . $columns . ' FROM ' . $sql->escapeIdentifier($tempTable)
    );
};

$deduplicateByPrimaryKey = static function (string $table, array $keyColumns, array $aggregates) use ($sql, $tableExists, $hasExpectedPrimaryKey, $hasDuplicateKeys): void {
    if (!$tableExists($table)) {
        return;
    }

    if ($hasExpectedPrimaryKey($table, $keyColumns)) {
        return;
    }

    if (!$hasDuplicateKeys($table, $keyColumns)) {
        return;
    }

    $quotedKeys = array_map([$sql, 'escapeIdentifier'], $keyColumns);
    $groupBy = implode(', ', $quotedKeys);

    $tempTable = $table . '_dedup_pk_tmp';
    $sql->setQuery('DROP TEMPORARY TABLE IF EXISTS ' . $sql->escapeIdentifier($tempTable));

    $selectParts = $quotedKeys;
    foreach ($aggregates as $column => $aggregateExpression) {
        $selectParts[] = $aggregateExpression . ' AS ' . $sql->escapeIdentifier($column);
    }

    $insertColumns = array_merge(
        array_map('strval', $keyColumns),
        array_map('strval', array_keys($aggregates))
    );
    $insertColumns = implode(', ', array_map([$sql, 'escapeIdentifier'], $insertColumns));
    $selectColumns = implode(', ', $selectParts);

    $sql->setQuery(
        'CREATE TEMPORARY TABLE ' . $sql->escapeIdentifier($tempTable) . ' AS '
        . 'SELECT ' . $selectColumns
        . ' FROM ' . $sql->escapeIdentifier($table)
        . ' GROUP BY ' . $groupBy
    );

    $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($table));
    $sql->setQuery(
        'INSERT INTO ' . $sql->escapeIdentifier($table)
        . ' (' . $insertColumns . ') '
        . 'SELECT ' . $insertColumns . ' FROM ' . $sql->escapeIdentifier($tempTable)
    );
};

// Reinstall kann auf Alt-Daten mit Duplikaten laufen; vor PK-Setzung aggregieren.
$deduplicateCountTable(rex::getTable('pagestats_data'), ['type', 'name']);
$deduplicateCountTable(rex::getTable('pagestats_visits_per_day'), ['date', 'domain']);
$deduplicateCountTable(rex::getTable('pagestats_visitors_per_day'), ['date', 'domain']);
$deduplicateCountTable(rex::getTable('pagestats_bot'), ['name', 'category', 'producer']);
$deduplicateCountTable(rex::getTable('pagestats_media'), ['url', 'date']);
$deduplicateCountTable(rex::getTable('pagestats_api'), ['name', 'date']);

// Legacy-Bestände können doppelte PK-Werte enthalten (z.B. nach früheren Schema-Ständen).
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_visits_per_url'),
    ['hash'],
    [
        'date' => 'MIN(`date`)',
        'url' => 'MIN(`url`)',
        'count' => 'SUM(`count`)',
    ]
);
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_visitors_per_url'),
    ['hash'],
    [
        'date' => 'MIN(`date`)',
        'url' => 'MIN(`url`)',
        'count' => 'SUM(`count`)',
    ]
);
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_urlstatus'),
    ['hash'],
    [
        'url' => 'MIN(`url`)',
        'status' => 'MIN(`status`)',
    ]
);
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_hash'),
    ['hash'],
    [
        'datetime' => 'MAX(`datetime`)',
    ]
);
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_referer'),
    ['hash'],
    [
        'referer' => 'MIN(`referer`)',
        'date' => 'MIN(`date`)',
        'count' => 'SUM(`count`)',
    ]
);
$deduplicateByPrimaryKey(
    rex::getTable('pagestats_sessionstats'),
    ['token'],
    [
        'lastpage' => 'MIN(`lastpage`)',
        'lastvisit' => 'MAX(`lastvisit`)',
        'visitduration' => 'MAX(`visitduration`)',
        'pagecount' => 'MAX(`pagecount`)',
    ]
);


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
    ->ensureColumn(new rex_sql_column('url_hash', 'char(32)', false, ''))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensureIndex(new rex_sql_index('date_url_hash', ['date', 'url_hash']))
    ->ensureIndex(new rex_sql_index('url_hash_date', ['url_hash', 'date']))
    ->ensure();

rex_sql_table::get(rex::getTable('pagestats_visitors_per_url'))
    ->ensureColumn(new rex_sql_column('hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(2048)'))
    ->ensureColumn(new rex_sql_column('url_hash', 'char(32)', false, ''))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensureIndex(new rex_sql_index('date_url_hash', ['date', 'url_hash']))
    ->ensureIndex(new rex_sql_index('url_hash_date', ['url_hash', 'date']))
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
    ->ensureColumn(new rex_sql_column('referer_hash', 'char(32)', false, ''))
    ->ensureColumn(new rex_sql_column('date', 'date'))
    ->ensureColumn(new rex_sql_column('count', 'int'))
    ->setPrimaryKey(['hash'])
    ->ensureIndex(new rex_sql_index('date_referer_hash', ['date', 'referer_hash']))
    ->ensureIndex(new rex_sql_index('referer_hash_date', ['referer_hash', 'date']))
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

// Backfill hash columns for existing installations before hash-based lookups are used.
$sql->setQuery(
    'UPDATE ' . $sql->escapeIdentifier(rex::getTable('pagestats_visits_per_url'))
    . ' SET url_hash = MD5(url) WHERE url_hash = ""'
);
$sql->setQuery(
    'UPDATE ' . $sql->escapeIdentifier(rex::getTable('pagestats_visitors_per_url'))
    . ' SET url_hash = MD5(url) WHERE url_hash = ""'
);
$sql->setQuery(
    'UPDATE ' . $sql->escapeIdentifier(rex::getTable('pagestats_referer'))
    . ' SET referer_hash = MD5(referer) WHERE referer_hash = ""'
);

// Remove legacy prefix indexes. rex_sql_table cannot reproduce their Sub_part metadata,
// while the hash indexes above are fully portable through REDAXO schema tooling/ydeploy.
$dropIndexIfExists = static function (string $table, string $indexName) use ($sql): void {
    $indexes = $sql->getArray(
        'SHOW INDEX FROM ' . $sql->escapeIdentifier($table) . ' WHERE Key_name = :index_name',
        ['index_name' => $indexName]
    );

    if ([] !== $indexes) {
        $sql->setQuery(
            'ALTER TABLE ' . $sql->escapeIdentifier($table)
            . ' DROP INDEX ' . $sql->escapeIdentifier($indexName)
        );
    }
};

foreach (['date_url', 'url_date'] as $indexName) {
    $dropIndexIfExists(rex::getTable('pagestats_visits_per_url'), $indexName);
    $dropIndexIfExists(rex::getTable('pagestats_visitors_per_url'), $indexName);
}
foreach (['url', 'url_status'] as $indexName) {
    $dropIndexIfExists(rex::getTable('pagestats_urlstatus'), $indexName);
}
foreach (['date_referer', 'referer_date'] as $indexName) {
    $dropIndexIfExists(rex::getTable('pagestats_referer'), $indexName);
}

// Geo-Datenbank wird bewusst nicht mehr automatisch im Install/Reinstall geladen.
// Die Aktualisierung erfolgt manuell ueber die Settings-Seite.
$addon = rex_addon::get('statistics');
$geoInstallNotice = trim((string) $addon->i18n('statistics_geo_install_notice'));
if ('' !== $geoInstallNotice) {
    $existingSuccessMessage = trim((string) $addon->getProperty('successmsg', ''));
    $addon->setProperty(
        'successmsg',
        '' !== $existingSuccessMessage ? $existingSuccessMessage . ' ' . $geoInstallNotice : $geoInstallNotice
    );
}

} finally {
    rex_config::set('statistics', 'statistics_pause_tracking_runtime', $previousRuntimePause);
}
