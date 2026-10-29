<?php

namespace AndiLeni\Statistics;

use DateTimeImmutable;
use rex;
use rex_sql;
use InvalidArgumentException;
use rex_sql_exception;


/**
 * Main class to handle saving of media requests
 *
 */
class MediaRequest
{

    // media urls, for media detection
    const MEDIA_URLS = [
        '/media',
        '/index.php?rex_media_type',
        '/index.php?rex_media_file',
    ];

    const MEDIA_TYPES = [
        '.jpg',
        '.jpeg',
        '.png',
        '.gif',
        '.webp',
        '.tiff',
        '.tif',
        '.ico',
        '.svg',
        '.wbmp',
        '.bmp',
        '.pdf',
        '.doc',
        '.xls',
        '.ppt',
        '.xla',
        '.pps',
        '.ppz',
        '.pot',
        '.dot',
        '.dotx',
        '.docx',
        '.xlsx',
        '.pptx',
        '.odt',
        '.ods',
        '.odp',
        '.odc',
        '.odf',
        '.odi',
        '.odm',
        '.mp3',
        '.mp4',
        '.avi',
        '.mpg',
        '.flv',
        '.ogg',
        '.ogv',
        '.swf',
        '.wmv',
        '.webm',
        '.mpeg',
        '.mov',
        '.qt',
        '.wav',
        '.3gp',
        '.js',
        '.css',
        '.gz',
        '.zip',
        '.rar',
        '.tar',
        '.gzip',
        '.json',
        '.xml',
        '.txt',
        '.csv',
    ];

    private string $url;
    private DateTimeImmutable $datetime_now;



    /**
     *
     *
     * @param string $url
     * @return void
     */
    public function __construct(string $url)
    {
        $this->url = $url;
        $this->datetime_now = new DateTimeImmutable();
    }


    /**
     * 
     * 
     * @return bool 
     */
    public function isMedia(): bool
    {
        foreach (self::MEDIA_URLS as $el) {
            if (str_starts_with($this->url, $el)) {
                return true;
            }
        }

        foreach (self::MEDIA_TYPES as $el) {
            if (str_ends_with($this->url, $el) || str_ends_with($this->url, strtoupper($el))) {
                return true;
            }
        }

        return false;
    }



    /**
     *
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function save(): void
    {
        // Media tracking runs in MEDIA_MANAGER_AFTER_SEND, i.e. after the file was already
        // streamed to the client. For large files (e.g. video downloads / range requests) the
        // transfer can outlive MySQL's wait_timeout, so the connection may be gone by the time
        // this counter UPDATE runs ("MySQL server has gone away", SQLSTATE[HY000] 2006).
        // One reconnect attempt covers that case; any remaining failure is swallowed so a counter
        // that cannot be written never turns into log spam on an otherwise successful media request.
        try {
            $this->persist();
        } catch (rex_sql_exception $e) {
            if ($this->isConnectionLost($e)) {
                try {
                    // Force a fresh connection and retry once.
                    rex_sql::factory()->setQuery('SELECT 1');
                    $this->persist();
                    return;
                } catch (rex_sql_exception $retry) {
                    // fall through – give up silently, see note above
                    return;
                }
            }
            // Non-connection errors are not expected here; keep them out of the log as well,
            // since a failed media counter must not break or spam an already-delivered response.
            return;
        }
    }


    /**
     * Writes the counter row: increment if it exists for today, otherwise insert.
     *
     * @return void
     * @throws rex_sql_exception
     */
    private function persist(): void
    {
        $sql = rex_sql::factory();
        $result = $sql->setQuery('UPDATE ' . rex::getTable('pagestats_media') . ' SET count = count + 1 WHERE url = :url AND date = :date', ['url' => $this->url, 'date' => $this->datetime_now->format('Y-m-d')]);

        if ($result->getRows() === 0) {
            $bot = rex_sql::factory();
            $bot->setTable(rex::getTable('pagestats_media'));
            $bot->setValue('url', $this->url);
            $bot->setValue('date', $this->datetime_now->format('Y-m-d'));
            $bot->setValue('count', 1);
            $bot->insert();
        }
    }


    /**
     * Detects the "MySQL server has gone away" condition (connection dropped mid-request).
     *
     * @param rex_sql_exception $e
     * @return bool
     */
    private function isConnectionLost(rex_sql_exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'server has gone away')
            || str_contains($message, '2006')
            || str_contains($message, '2013'); // Lost connection to MySQL server during query
    }
}
