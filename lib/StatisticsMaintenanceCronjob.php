<?php

class rex_statistics_maintenance_cronjob extends rex_cronjob
{
    public function execute(): bool
    {
        $daysToKeepRaw = max(1, (int) $this->getParam('days_to_keep_raw', 120));
        $optimizeTables = (int) $this->getParam('optimize_tables', 0) === 1;
        $optimizeBatchSize = max(1, (int) $this->getParam('optimize_batch_size', 2));

        $cutoffDate = (new DateTimeImmutable('today'))->modify('-' . $daysToKeepRaw . ' days')->format('Y-m-d');
        $cutoffDatetime = $cutoffDate . ' 00:00:00';

        $deleted = 0;

        try {
            $deleted += $this->deleteChunked(rex::getTable('pagestats_visits_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_visitors_per_url'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_referer'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_media'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_api'), 'date < :cutoff_date', [':cutoff_date' => $cutoffDate]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_sessionstats'), 'lastvisit < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);
            $deleted += $this->deleteChunked(rex::getTable('pagestats_hash'), 'datetime < :cutoff_datetime', [':cutoff_datetime' => $cutoffDatetime]);

            $deleted += $this->deleteOrphanUrlStatusChunked();

            $optimized = 0;
            $optimizedTotal = 0;
            $optimizedRemaining = 0;
            if ($optimizeTables) {
                $optimizeResult = $this->optimizeTablesBatch($this->getTablesToOptimize(), $optimizeBatchSize);
                $optimized = $optimizeResult['optimized'];
                $optimizedTotal = $optimizeResult['total'];
                $optimizedRemaining = $optimizeResult['remaining'];
            }

            $message = 'Statistics-Wartung: ' . $deleted . ' Rohdaten-Einträge bereinigt (älter als ' . $daysToKeepRaw . ' Tage)';
            if ($optimizeTables) {
                $message .= ', ' . $optimized . ' Tabellen optimiert (Batchgröße: ' . $optimizeBatchSize . ')';
                if ($optimizedTotal > 0) {
                    $message .= ', verbleibend bis Vollzyklus: ' . $optimizedRemaining . ' von ' . $optimizedTotal;
                }
            }
            $this->setMessage($message);

            return true;
        } catch (rex_sql_exception $exception) {
            $this->setMessage('Statistics-Wartung fehlgeschlagen: ' . $exception->getMessage());

            return false;
        }
    }

    public function getTypeName(): string
    {
        return rex_i18n::msg('statistics_cron_maintenance_type');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getParamFields(): array
    {
        return [
            [
                'label' => rex_i18n::msg('statistics_cron_maintenance_keep_days'),
                'name' => 'days_to_keep_raw',
                'type' => 'select',
                'default' => 120,
                'options' => [
                    30 => '30',
                    60 => '60',
                    90 => '90',
                    120 => '120',
                    180 => '180',
                    365 => '365',
                    730 => '730',
                ],
            ],
            [
                'label' => rex_i18n::msg('statistics_cron_maintenance_optimize'),
                'name' => 'optimize_tables',
                'type' => 'select',
                'default' => 0,
                'options' => [
                    0 => rex_i18n::msg('statistics_no'),
                    1 => rex_i18n::msg('statistics_yes'),
                ],
            ],
            [
                'label' => rex_i18n::msg('statistics_cron_maintenance_optimize_batch_size'),
                'name' => 'optimize_batch_size',
                'type' => 'select',
                'default' => 2,
                'options' => [
                    1 => '1',
                    2 => '2',
                    3 => '3',
                    4 => '4',
                    5 => '5',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getTablesToOptimize(): array
    {
        return [
            rex::getTable('pagestats_hash'),
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
    }

    /**
     * @param array<int, string> $tablesToOptimize
     *
     * @return array{optimized: int, total: int, remaining: int}
     */
    private function optimizeTablesBatch(array $tablesToOptimize, int $batchSize): array
    {
        $total = count($tablesToOptimize);
        if (0 === $total) {
            return [
                'optimized' => 0,
                'total' => 0,
                'remaining' => 0,
            ];
        }

        $batchSize = max(1, min($batchSize, $total));
        $addon = rex_addon::get('statistics');
        $cursorKey = 'maintenance_optimize_cursor';
        $cursor = max(0, (int) $addon->getConfig($cursorKey, 0));
        if ($cursor >= $total) {
            $cursor = 0;
        }

        $startCursor = $cursor;
        $optimized = 0;

        while ($optimized < $batchSize) {
            $tableName = $tablesToOptimize[$cursor];
            $sql = rex_sql::factory();
            $sql->setQuery('OPTIMIZE TABLE ' . $tableName);

            ++$optimized;
            ++$cursor;

            if ($cursor >= $total) {
                $cursor = 0;
            }
        }

        $addon->setConfig($cursorKey, $cursor);

        $completedCycle = $cursor === $startCursor;
        $remaining = $completedCycle ? 0 : $total - $optimized;

        return [
            'optimized' => $optimized,
            'total' => $total,
            'remaining' => max(0, $remaining),
        ];
    }

    /**
     * @param array<string, scalar> $params
     */
    private function deleteChunked(string $table, string $condition, array $params = [], int $chunkSize = 5000): int
    {
        $total = 0;

        do {
            $affected = $this->runDeleteWithRetry(
                'DELETE FROM ' . $table . ' WHERE ' . $condition . ' LIMIT ' . (int) $chunkSize,
                $params
            );
            $total += $affected;
        } while ($affected >= $chunkSize);

        return $total;
    }

    private function deleteOrphanUrlStatusChunked(int $chunkSize = 5000): int
    {
        $total = 0;

        do {
            $affected = $this->runDeleteWithRetry(
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
    }

    /**
     * @param array<string, scalar> $params
     */
    private function runDeleteWithRetry(string $query, array $params = []): int
    {
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
    }
}
