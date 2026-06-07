<?php

$addon = rex_addon::get('statistics');

$sql = rex_sql::factory();

$tableExists = static function (string $table): bool {
    if ('' === $table) {
        return false;
    }

    return rex_sql_table::get($table)->exists();
};

$deduplicateCountTable = static function (string $table, array $keyColumns, string $countColumn = 'count') use ($sql, $tableExists): void {
    if (!$tableExists($table)) {
        return;
    }

    $quotedKeys = array_map([$sql, 'escapeIdentifier'], $keyColumns);
    $groupBy = implode(', ', $quotedKeys);
    $columns = implode(', ', array_merge($quotedKeys, [$sql->escapeIdentifier($countColumn)]));

    $duplicates = $sql->getValue(
        'SELECT COUNT(*) FROM ('
        . 'SELECT 1 FROM ' . $sql->escapeIdentifier($table)
        . ' GROUP BY ' . $groupBy
        . ' HAVING COUNT(*) > 1'
        . ') AS duplicate_rows'
    );

    if ((int) $duplicates === 0) {
        return;
    }

    $tempTable = $table . '_dedup_tmp';
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

$deduplicateCountTable(rex::getTable('pagestats_visits_per_day'), ['date', 'domain']);
$deduplicateCountTable(rex::getTable('pagestats_visitors_per_day'), ['date', 'domain']);
$deduplicateCountTable(rex::getTable('pagestats_data'), ['type', 'name']);
$deduplicateCountTable(rex::getTable('pagestats_media'), ['url', 'date']);
$deduplicateCountTable(rex::getTable('pagestats_api'), ['name', 'date']);
$deduplicateCountTable(rex::getTable('pagestats_bot'), ['name', 'category', 'producer']);

// create tables
$addon->includeFile(__DIR__ . '/install.php');


// version 3 migrations

// copy old config settings
if (rex_config::has("statistics/api", "statistics_api_enable")) {
    rex_config::set("statistics", "statistics_api_enable", rex_config::get("statistics/api", "statistics_api_enable"));
}

if (rex_config::has("statistics/media", "statistics_media_log_all")) {
    rex_config::set("statistics", "statistics_media_log_all", rex_config::get("statistics/media", "statistics_media_log_all"));
}

if (rex_config::has("statistics/media", "statistics_media_log_mm")) {
    rex_config::set("statistics", "statistics_media_log_mm", rex_config::get("statistics/media", "statistics_media_log_mm"));
}

// remove plugins
rex_dir::delete(rex_path::addon('statistics', 'plugins'));
rex_package_manager::synchronizeWithFileSystem();
