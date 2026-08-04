<?php

use AndiLeni\Statistics\EventRequest;


/**
 * API class
 *
 */
class rex_api_stats extends rex_api_function
{

    protected $published = true;


    /**
     * 
     * 
     * @return rex_api_result 
     * @throws InvalidArgumentException 
     * @throws Exception 
     * @throws rex_sql_exception 
     */
    public function execute(): rex_api_result
    {

        $addon = rex_addon::get('statistics');
        $trackingPaused = (bool) $addon->getConfig('statistics_pause_tracking_manual', false)
            || (bool) $addon->getConfig('statistics_pause_tracking_runtime', false)
            || (bool) $addon->getConfig('statistics_pause_tracking', false);
        if ($trackingPaused) {
            return new rex_api_result(true);
        }

        if ($addon->getConfig('statistics_api_enable') == true) {

            require_once __DIR__ . '/../../vendor/autoload.php';

            // get ip from visitor, set to 0.0.0.0 when ip can not be determined
            $clientAddress = rex::getRequest()->getClientIp();
            $clientAddress = $clientAddress ? $clientAddress : '0.0.0.0';

            // campaign url
            $name = rex_request('name', 'string', '');

            if ($name != '') {

                // user agent
                $userAgent = rex_server('HTTP_USER_AGENT', 'string', '');

                $visit = new EventRequest($clientAddress, $name, $userAgent);

                // parse useragent
                $visit->parseUA();

                if (!$visit->isBot() && $visit->shouldSave()) {

                    $visit->save();
                }


                $result = new rex_api_result(true);
            } else {
                $result = new rex_api_result(false);
            }

            return $result;
        }
        $result = new rex_api_result(true);
        return $result;
    }
}
