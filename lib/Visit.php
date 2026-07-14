<?php

namespace AndiLeni\Statistics;

use DateTime;
use DateTimeImmutable;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Yaml\Symfony as DeviceDetectorSymfonyYamlParser;
use rex;
use rex_addon;
use rex_path;
use rex_sql;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use InvalidArgumentException;
use rex_sql_exception;
use Exception;
use GeoIp2\Database\Reader;
use rex_logger;
use Matomo\Network\IP;

/**
 * Main class to handle saving of page visitors.
 * Performs checks to decide if visit should be ignored
 *
 */
class Visit
{

    const IGNORE_WHEN_STARTS = [
        '/robots.txt',
        '/sitemap.xml',
    ];

    const IGNORE_WHEN_CONTAINS = [
        'rex_version=1',
        'search_it_build_index',
        'rex-api-call',
    ];

    const IGNORE_WHEN_ENDS = [
        '.css',
        '.js',
        'favicon.ico',
        '.css.map',
        '.js.map',
    ];

    const IGNORE_UA = [
        'REDAXO',
    ];

    const BOTIPS = [
        // Live/Bing/MSN
        '64.4.0.0/18',
        '65.52.0.0/14',
        '157.54.0.0/15',
        '157.56.0.0/14',
        '157.60.0.0/16',
        '207.46.0.0/16',
        '207.68.128.0/18',
        '207.68.192.0/20',
        '131.253.26.0/20',
        '131.253.24.0/20',
        // Yahoo
        '72.30.198.0/20',
        '72.30.196.0/20',
        '98.137.207.0/20',
        // Chinese bot hammering websites
        '1.202.218.8',
        // google
        '216.239.32.0/19',
        '64.233.160.0/19',
        '66.249.80.0/20',
        '72.14.192.0/18',
        '209.85.128.0/17',
        '66.102.0.0/20',
        '74.125.0.0/16',
        '64.18.0.0/20',
        '207.126.144.0/20',
        '173.194.0.0/16',
    ];


    private DateTimeImmutable $datetime_now;

    private rex_addon $addon;

    private string $clientIPAddress;

    private string $url;

    private string $userAgent;

    public DeviceDetector $DeviceDetector;

    private string $browser = 'Undefiniert';

    private string $os = 'Undefiniert';

    private string $osVer = 'Undefiniert';

    private string $device_type = 'Undefiniert';

    private string $brand = 'Undefiniert';

    private string $model = 'Undefiniert';

    private string $domain = '';

    private string $token = '';

    private string $httpStatus = '';

    private string $country = '';


    /**
     * 
     * 
     * @param string $clientIPAddress 
     * @param string $url 
     * @param string $userAgent 
     * @param string $domain 
     * @param string $token
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct(string $clientIPAddress, string $url, string $userAgent, string $domain, string $token, string $httpStatus)
    {
        $this->addon = rex_addon::get('statistics');
        $this->clientIPAddress = $clientIPAddress;
        $this->url = $url;
        $this->datetime_now = new DateTimeImmutable();
        $this->userAgent = $userAgent;
        $this->domain = $domain;
        $this->token = $token;
        $this->httpStatus = $httpStatus;
    }



    /**
     * 
     * 
     * @return bool 
     * @throws InvalidArgumentException 
     */
    public function shouldIgnore(): bool
    {
        // check if visit should be ignored
        $ignored_paths = $this->addon->getConfig('statistics_ignored_paths');
        $ignored_ips = $this->addon->getConfig('statistics_ignored_ips');
        $ignored_regex = $this->addon->getConfig('pagestats_ignored_regex');

        /**
         * check bot ips
         * adapted from: https://github.com/matomo-org/matomo/blob/5.x-dev/core/Tracker/VisitExcluded.php
         */
        $ip = IP::fromStringIP($this->clientIPAddress);

        if ($this->isChromeDataSaverUsed($ip)) {
            $isInRanges = false;
        } else {
            $isInRanges = $ip->isInRanges(self::BOTIPS);
        }

        if ($isInRanges) {
            return true;
        }

        // check own ignored ips
        if (trim($ignored_ips != '')) {
            $ignored_ips = explode("\n", str_replace("\r", "", $ignored_ips));

            foreach ($ignored_ips as $ip) {
                if (str_starts_with($this->clientIPAddress, $ip)) {
                    return true;
                }
            }
        }

        if (trim($ignored_paths != '')) {
            $ignored_paths = explode("\n", str_replace("\r", "", $ignored_paths));

            foreach ($ignored_paths as $path) {
                if (str_starts_with($this->url, $path)) {
                    return true;
                }
            }
        }

        foreach (self::IGNORE_WHEN_ENDS as $el) {
            if (str_ends_with($this->url, $el)) {
                return true;
            }
        }

        foreach (self::IGNORE_WHEN_STARTS as $el) {
            if (str_starts_with($this->url, $el)) {
                return true;
            }
        }

        foreach (self::IGNORE_WHEN_CONTAINS as $el) {
            if (str_contains($this->url, $el)) {
                return true;
            }
        }

        if (trim($ignored_regex != '')) {
            $ignored_regex = explode("\n", str_replace("\r", "", $ignored_regex));

            foreach ($ignored_regex as $regex) {
                if (preg_match($regex, $this->url) === 1) {
                    return true;
                }
            }
        }

        // check special user agents which should be ignored
        foreach (self::IGNORE_UA as $el) {
            if (str_starts_with($this->userAgent, $el)) {
                return true;
            }
        }


        return false;
    }

    public function isChromeDataSaverUsed(IP $ip)
    {
        // see https://github.com/piwik/piwik/issues/7733
        return !empty($_SERVER['HTTP_VIA'])
            && false !== strpos(strtolower($_SERVER['HTTP_VIA']), 'chrome-compression-proxy')
            && $ip->isInRanges(self::BOTIPS);
    }


    /**
     * 
     * 
     * @return void 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function persist(): void
    {
        $this->getCountry();

        $clientInfo = $this->DeviceDetector->getClient();
        $osInfo = $this->DeviceDetector->getOs();
        $deviceInfo = $this->DeviceDetector->getDeviceName();
        $brandInfo = $this->DeviceDetector->getBrandName();
        $modelInfo = $this->DeviceDetector->getModel();

        $this->browser = $clientInfo['name'] ?? 'Undefiniert';
        $this->os = $osInfo['name'] ?? 'Undefiniert';
        $this->osVer = $osInfo['version'] ?? 'Undefiniert';
        $this->device_type = trim($deviceInfo) != '' ? ucfirst($deviceInfo) : 'Undefiniert';
        $this->brand = trim($brandInfo) != '' ? ucfirst($brandInfo) : 'Undefiniert';
        $this->model = trim($modelInfo) != '' ? ucfirst($modelInfo) : 'Undefiniert';


        $sql = rex_sql::factory();

        $sql_insert = 'INSERT INTO ' . rex::getTable('pagestats_data') . ' (type,name,count) VALUES 
        ("browser","' . addslashes($this->browser) . '",1), 
        ("os","' . addslashes($this->os) . ' ' . addslashes($this->osVer) . '",1), 
        ("browsertype","' . addslashes($this->device_type) . '",1), 
        ("brand","' . addslashes($this->brand) . '",1), 
        ("model","' . addslashes($this->brand) . ' - ' . addslashes($this->model) . '",1),  
        ("hour","' . $this->datetime_now->format('H') . '",1), 
        ("weekday","' . $this->datetime_now->format('N') . '",1),
        ("country","' . $this->country . '",1)
        ON DUPLICATE KEY UPDATE count = count + 1;';

        $sql->setQuery($sql_insert);


        $sql_insert = 'INSERT INTO ' . rex::getTable('pagestats_visits_per_day') . ' (date,domain,count) VALUES 
        ("' . $this->datetime_now->format('Y-m-d') . '","' . addslashes($this->domain) . '",1)  
        ON DUPLICATE KEY UPDATE count = count + 1;';

        $sql->setQuery($sql_insert);
    }


    /**
     * 
     * 
     * @return void 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function updateVisitsPerUrl(): void
    {
        $sql = rex_sql::factory();

        $sql_insert = 'INSERT INTO ' . rex::getTable('pagestats_visits_per_url') . ' (hash,date,url,count) VALUES 
        ("' . md5($this->datetime_now->format('Y-m-d') . $this->url) . '","' . $this->datetime_now->format('Y-m-d') . '","' . addslashes($this->url) . '",1) 
        ON DUPLICATE KEY UPDATE count = count + 1;';

        $sql->setQuery($sql_insert);


        // save url http status
        $hash = md5($this->url);

        $sql = rex_sql::factory();
        $sql->setQuery("insert into " . rex::getTable("pagestats_urlstatus") . " (hash, url, status) values (:hash, :url, :status) on duplicate key update status = values(status);", [":hash" => $hash, ":url" => $this->url, ":status" => $this->httpStatus]);
    }


    public function getCountry(): void
    {
        $cityDbReader = new Reader($this->addon->getDataPath("ip2geo.mmdb"));
        try {
            $record = $cityDbReader->country($this->clientIPAddress);
            $this->country = $record->country->name;
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            $this->country = "Unbekannt";
        } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
            rex_logger::logException($e);
        }
    }


    /**
     * 
     * 
     * @return void 
     * @throws InvalidArgumentException 
     * @throws rex_sql_exception 
     */
    public function persistVisitor(): void
    {
        $sql = rex_sql::factory();

        $sql_insert = 'INSERT INTO ' . rex::getTable('pagestats_visitors_per_day') . ' (date,domain,count) VALUES 
        ("' . $this->datetime_now->format('Y-m-d') . '","' . addslashes($this->domain) . '",1)  
        ON DUPLICATE KEY UPDATE count = count + 1;';

        $sql->setQuery($sql_insert);
    }


    /**
     *
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function shouldSaveVisit(): bool
    {
        $save_visit = true;

        $hash_string = $this->token . $this->url;
        $hash = hash('sha1', $hash_string);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('pagestats_hash'));
        $sql->setWhere("hash = :hash LIMIT 1", ['hash' => $hash]);
        $sql->select();

        if ($sql->getRows() == 1) {
            $origin = new DateTime($sql->getValue('datetime'));
            $target = new DateTime();
            $interval = $origin->diff($target);
            $minute_diff = $interval->i + ($interval->h * 60) + ($interval->d * 3600) + ($interval->m * 43800) + ($interval->y * 525599);

            // hash was found, if last visit < 'statistics_visit_duration' min save visit
            $max_visit_length = intval($this->addon->getConfig('statistics_visit_duration'));

            // if visit is not older than 'statistics_visit_duration' do not save visit
            if ($minute_diff <= $max_visit_length) {
                $save_visit = false;
            }
        }

        if ($save_visit) {
            // insert hash with current datetime if not found or update if found in database
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('pagestats_hash'));
            $sql->setValue('hash', $hash);
            $sql->setValue('datetime', $this->datetime_now->format('Y-m-d H:i:s'));
            $sql->insertOrUpdate();
        }


        return $save_visit;
    }


    /**
     *
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function shouldSaveVisitor(): bool
    {
        $save_visitor = true;

        $hash_string = $this->clientIPAddress . $this->userAgent;
        $hash = hash('sha1', $hash_string);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('pagestats_hash'));
        $sql->setWhere("hash = :hash LIMIT 1", ['hash' => $hash]);
        $sql->select();

        if ($sql->getRows() == 1) {
            $origin = new DateTime($sql->getValue('datetime'));
            $today = new DateTime('today midnight');

            // hash was found and last visit was today, do not save visitor
            if ($origin->format('d.m.Y') == $today->format('d.m.Y')) {
                $save_visitor = false;
            }
        }

        if ($save_visitor) {
            // insert hash with current datetime if not found or update if found in database
            $today = new DateTime('today midnight');
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('pagestats_hash'));
            $sql->setValue('hash', $hash);
            $sql->setValue('datetime', $today->format('Y-m-d H:i:s'));
            $sql->insertOrUpdate();
        }

        return $save_visitor;
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
        // $this->DeviceDetector = new DeviceDetector($this->userAgent);
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
    public function saveBot(): void
    {
        $botInfo = $this->DeviceDetector->getBot();

        $botname = $botInfo['name'] ?? '-';
        $botcategory = $botInfo['category'] ?? '-';
        $botproducer = $botInfo['producer']['name'] ?? '-';

        $sql = rex_sql::factory();

        $sql->setQuery('
        INSERT INTO ' . rex::getTable('pagestats_bot') . ' (name,category,producer,count) VALUES 
        (:botname,:botcategory,:botproducer,1) 
        ON DUPLICATE KEY UPDATE count = count + 1;', ['botname' => $botname, 'botcategory' => $botcategory, 'botproducer' => $botproducer]);
    }


    /**
     *
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function saveCrawlerDetect($name): void
    {
        $sql = rex_sql::factory();

        $sql->setQuery('
        INSERT INTO ' . rex::getTable('pagestats_bot') . ' (name,category,producer,count) VALUES 
        (:botname,:botcategory,:botproducer,1) 
        ON DUPLICATE KEY UPDATE count = count + 1;', ['botname' => $name, 'botcategory' => "Crawler", 'botproducer' => "-"]);
    }


    /**
     *
     *
     * @param string $referer
     * @return void
     * @throws InvalidArgumentException
     * @throws rex_sql_exception
     */
    public function saveReferer(string $referer): void
    {
        $sql = rex_sql::factory();

        $sql->setQuery('
        INSERT INTO ' . rex::getTable('pagestats_referer') . ' (hash,referer,date,count) VALUES 
        (:hash,:referer,:date,1) 
        ON DUPLICATE KEY UPDATE count = count + 1;', ['hash' => md5($this->datetime_now->format('Y-m-d') . $referer), 'referer' => $referer, 'date' => $this->datetime_now->format('Y-m-d')]);
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

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }


    /**
     * 
     * 
     * @param string $url 
     * @return string 
     */
    public static function removeUrlParameters(string $url): string
    {
        $url = strtok($url, '?');

        if ($url === false) {
            return '/';
        }

        return $url;
    }
}
