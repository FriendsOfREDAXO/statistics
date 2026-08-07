<?php

namespace AndiLeni\Statistics;

use rex;
use rex_sql;

final class DataTypeAggregationRepository
{
    /**
     * @var null|array<string, array<int, array{name: string, count: int}>>
     */
    private static ?array $cache = null;

    /**
     * @param string $type
     * @return array<int, array{name: string, count: int}>
     */
    public static function getRowsByType(string $type): array
    {
        self::ensureLoaded();

        return self::$cache[$type] ?? [];
    }

    private static function ensureLoaded(): void
    {
        if (null !== self::$cache) {
            return;
        }

        self::$cache = [];
        $types = ['browser', 'brand', 'browsertype', 'os', 'model', 'country', 'hour', 'weekday'];
        $quotedTypes = array_map(static fn(string $type): string => '"' . $type . '"', $types);

        $sql = rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT type, name, count'
            . ' FROM ' . rex::getTable('pagestats_data')
            . ' WHERE type IN (' . implode(', ', $quotedTypes) . ')'
            . ' ORDER BY type ASC, count DESC'
        );

        foreach ($types as $type) {
            self::$cache[$type] = [];
        }

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (!isset(self::$cache[$type])) {
                continue;
            }

            self::$cache[$type][] = [
                'name' => (string) ($row['name'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }
    }
}
