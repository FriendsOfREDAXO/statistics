<?php

namespace AndiLeni\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use rex_file;
use rex_logger;
use rex_path;
use rex_socket;
use rex_socket_exception;


class Ip2Geo
{
    /**
     * 
     * 
     * @return bool 
     * @throws InvalidArgumentException 
     */
    public static function updateDatabase(): bool
    {
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
    }
}
