<?php

namespace AndiLeni\Statistics;

class StatsLazySection
{
    public static function render(DateFilter $filterDateHelper): string
    {
        return self::renderLazyBlock('statistics_lazy_device', 'device', $filterDateHelper)
            . self::renderLazyBlock('statistics_lazy_extended', 'extended', $filterDateHelper)
            . self::renderLazyBlock('statistics_lazy_bots', 'bots', $filterDateHelper);
    }

    private static function renderLazyBlock(string $id, string $blockId, DateFilter $filterDateHelper): string
    {
        return '<div id="' . rex_escape($id) . '" data-statistics-lazy-block data-block-id="' . rex_escape($blockId) . '" data-date-start="' . rex_escape($filterDateHelper->date_start->format('Y-m-d')) . '" data-date-end="' . rex_escape($filterDateHelper->date_end->format('Y-m-d')) . '" data-state="idle" style="min-height: 160px;"></div>';
    }
}
