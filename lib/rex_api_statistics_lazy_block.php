<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\StatsLazyBlockRenderer;

class rex_api_statistics_lazy_block extends rex_api_function
{
    protected $published = false;

    public function execute()
    {
        $blockId = rex_request('block_id', 'string', '');
        $dateStart = rex_request('date_start', 'string', '');
        $dateEnd = rex_request('date_end', 'string', '');

        rex_response::cleanOutputBuffers();
        rex_response::sendContentType('application/json; charset=utf-8');

        if (!in_array($blockId, ['device', 'device-hourly', 'extended', 'extended-pagecount', 'extended-visitduration', 'extended-lastpage', 'extended-country', 'bots', 'main-daily-tables', 'main-monthly-tables', 'main-yearly-tables', 'main-monthly-chart', 'main-yearly-chart'], true)) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'Unknown block id']);
            exit;
        }

        try {
            $filter = new DateFilter($dateStart, $dateEnd, 'pagestats_visits_per_day');
            $renderer = new StatsLazyBlockRenderer($filter);
            rex_response::setStatus(rex_response::HTTP_OK);
            rex_response::sendJson($renderer->render($blockId));
        } catch (Throwable $throwable) {
            rex_logger::logException($throwable);
            rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
            rex_response::sendJson(['error' => 'Unable to render statistics block']);
        }

        exit;
    }
}