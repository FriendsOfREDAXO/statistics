<?php

class rex_statistics_maintenance_cronjob extends rex_cronjob
{
    public function execute(): bool
    {
        $daysToKeepRaw = max(1, (int) $this->getParam('days_to_keep_raw', 120));
        $optimizeTables = (int) $this->getParam('optimize_tables', 0) === 1;

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
            if ($optimizeTables) {
                $tablesToOptimize = [
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

                foreach ($tablesToOptimize as $tableName) {
                    $sql = rex_sql::factory();
                    $sql->setQuery('OPTIMIZE TABLE ' . $tableName);
                    ++$optimized;
                }
            }

            $message = 'Statistics-Wartung: ' . $deleted . ' Rohdaten-Einträge bereinigt (älter als ' . $daysToKeepRaw . ' Tage)';
            if ($optimizeTables) {
                $message .= ', ' . $optimized . ' Tabellen optimiert';
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
