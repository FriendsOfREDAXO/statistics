<?php

namespace AndiLeni\Statistics;

use rex_fragment;

class StatsSubpageRenderer
{
    public static function renderFilter(string $currentBackendPage, DateFilter $filterDateHelper): string
    {
        $filterFragment = new rex_fragment();
        $filterFragment->setVar('current_backend_page', $currentBackendPage);
        $filterFragment->setVar('date_start', $filterDateHelper->date_start);
        $filterFragment->setVar('date_end', $filterDateHelper->date_end);
        $filterFragment->setVar('wts', $filterDateHelper->whole_time_start->format('Y-m-d'));

        return '<div class="row"><div class="col-sm-12">' . $filterFragment->parse('filter.php') . '</div></div>' . StatsDashboard::renderTableLanguageConfigScript();
    }

    public static function renderInfoSection(string $title, string $heading, string $body): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('class', 'info', false);
        $fragment->setVar('title', rex_escape($title));
        $fragment->setVar('heading', rex_escape($heading));
        $fragment->setVar('body', $body, false);

        return $fragment->parse('core/page/section.php');
    }

    public static function renderSection(string $title, string $body): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', rex_escape($title));
        $fragment->setVar('body', $body, false);

        return $fragment->parse('core/page/section.php');
    }
}
