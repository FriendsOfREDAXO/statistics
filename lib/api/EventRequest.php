<?php

namespace AndiLeni\Statistics;

use DateTime;
use DateTimeImmutable;
use DeviceDetector\Cache\StaticCache;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Yaml\Symfony as DeviceDetectorSymfonyYamlParser;
use rex;
use rex_addon;
use rex_addon_interface;
use rex_sql;
use InvalidArgumentException;
use rex_sql_exception;
use Exception;


/**
 * Main class to handle saving of page visitors.
 * Performs checks to decide if visit should be ignored
 *
 */
class EventRequest
{


    private DateTimeImmutable $datetime_now;
    private rex_addon_interface $addon;

    private string $clientIPAddress;
    private string $name;
    private string $userAgent;
    private DeviceDetector $DeviceDetector;


    /**
     *
     *
     * @param string $clientIPAddress
     * @param string $userAgent
     * @return void
     * @throws InvalidArgumentException
     */
    public function __construct(string $clientIPAddress, string $name, string $userAgent)
    {
        $this->addon = rex_addon::get('statistics');
        $this->clientIPAddress = $clientIPAddress;
        $this->name = $name;
        $this->datetime_now = new DateTimeImmutable();
        $this->userAgent = $userAgent;
    }


    /**
     *
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function shouldSave(): bool
    {
        $hash_string = $this->userAgent . $this->clientIPAddress . $this->name;
        $hash = hash('sha1', $hash_string);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('pagestats_hash'));
        $sql->setWhere(['hash' => $hash]);
        $sql->select();

        if ($sql->getRows() == 1) {
            $origin = new DateTime((string) $sql->getValue('datetime'));
            $target = new DateTime();
            $interval = $origin->diff($target);
            $minute_diff = $interval->i + ($interval->h * 60) + ($interval->d * 3600) + ($interval->m * 43800) + ($interval->y * 525599);

            // hash was found, if last visit < 'statistics_visit_duration' min save visit
            $max_visit_length = intval($this->addon->getConfig('statistics_visit_duration'));

            if ($minute_diff > $max_visit_length) {
                $this->touchHashEntry($hash, $this->datetime_now->format('Y-m-d H:i:s'));
                return true;
            } else {
                return false;
            }
        } else {
            // hash was not found, save hash with current datetime, then save visit
            $this->touchHashEntry($hash, $this->datetime_now->format('Y-m-d H:i:s'));

            return true;
        }
    }

    private function touchHashEntry(string $hash, string $datetime): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('pagestats_hash'));
        $sql->setValue('hash', $hash);
        $sql->setValue('datetime', $datetime);
        $sql->insertOrUpdate();
    }


    /**
     *
     *
     * @return void
     * @throws Exception
     */
    public function parseUA(): void
    {
        $cache = new StaticCache();
        $clientHints = ClientHints::factory(self::buildClientHintsServerBag());
        $this->DeviceDetector = new DeviceDetector($this->userAgent, $clientHints);
        $this->DeviceDetector->setYamlParser(new DeviceDetectorSymfonyYamlParser());
        $this->DeviceDetector->setCache($cache);
        $this->DeviceDetector->parse();
    }

    /**
     * @return array<string, string>
     */
    private static function buildClientHintsServerBag(): array
    {
        $keys = [
            'HTTP_USER_AGENT',
            'HTTP_SEC_CH_UA',
            'HTTP_SEC_CH_UA_MOBILE',
            'HTTP_SEC_CH_UA_PLATFORM',
            'HTTP_SEC_CH_UA_PLATFORM_VERSION',
            'HTTP_SEC_CH_UA_MODEL',
            'HTTP_SEC_CH_UA_FULL_VERSION',
            'HTTP_SEC_CH_UA_FULL_VERSION_LIST',
            'HTTP_SEC_CH_UA_ARCH',
            'HTTP_SEC_CH_UA_BITNESS',
        ];

        $server = [];

        foreach ($keys as $key) {
            $value = rex_server($key, 'string', '');
            if ('' !== $value) {
                $server[$key] = $value;
            }
        }

        return $server;
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
        $this->incrementCounterRow(
            rex::getTable('pagestats_api'),
            [
                'name' => $this->name,
                'date' => $this->datetime_now->format('Y-m-d'),
            ]
        );
    }

    /**
     * @param array<string, scalar|null> $keyValues
     */
    private function incrementCounterRow(string $table, array $keyValues): void
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($keyValues as $column => $value) {
            $columns[] = $column;
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        $query = 'INSERT INTO ' . $table
            . ' (' . implode(',', $columns) . ',count)'
            . ' VALUES (' . implode(',', $placeholders) . ',1)'
            . ' ON DUPLICATE KEY UPDATE count = count + 1;';

        $sql = rex_sql::factory();
        $sql->setQuery($query, $params);
    }


    /**
     * 
     * 
     * @return bool 
     */
    public function isBot(): bool
    {
        return $this->DeviceDetector->isBot();
    }
}
