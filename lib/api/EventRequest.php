<?php

namespace AndiLeni\Statistics;

use DateTime;
use DateTimeImmutable;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Yaml\Symfony as DeviceDetectorSymfonyYamlParser;
use rex;
use rex_addon;
use rex_addon_interface;
use rex_path;
use rex_sql;
use InvalidArgumentException;
use rex_sql_exception;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
                // update set last visit to now
                $sql->setQuery('UPDATE ' . rex::getTable('pagestats_hash') . ' SET datetime = :datetime WHERE hash = :hash ', ['hash' => $hash, 'datetime' => $this->datetime_now->format('Y-m-d H:i:s')]);
                return true;
            } else {
                return false;
            }
        } else {
            // hash was not found, save hash with current datetime, then save visit
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('pagestats_hash'));
            $sql->setValue('hash', $hash);
            $sql->setValue('datetime', $this->datetime_now->format('Y-m-d H:i:s'));
            $sql->insert();

            return true;
        }
    }


    /**
     *
     *
     * @return void
     * @throws Exception
     */
    public function parseUA(): void
    {
        $cache = new FilesystemAdapter('', 0, rex_path::addonCache('statistics', 'devicedetector'));
        $clientHints = ClientHints::factory($_SERVER);
        $this->DeviceDetector = new DeviceDetector($this->userAgent, $clientHints);
        $this->DeviceDetector->setYamlParser(new DeviceDetectorSymfonyYamlParser());
        $this->DeviceDetector->setCache(new \DeviceDetector\Cache\PSR6Bridge($cache));
        $this->DeviceDetector->parse();
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
        $sql = rex_sql::factory();
        $result = $sql->setQuery('UPDATE ' . rex::getTable('pagestats_api') . ' SET count = count + 1 WHERE name = :name AND date = :date', ['name' => $this->name, 'date' => $this->datetime_now->format('Y-m-d')]);

        if ($result->getRows() === 0) {
            $bot = rex_sql::factory();
            $bot->setTable(rex::getTable('pagestats_api'));
            $bot->setValue('name', $this->name);
            $bot->setValue('date', $this->datetime_now->format('Y-m-d'));
            $bot->setValue('count', 1);
            $bot->insert();
        }
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
