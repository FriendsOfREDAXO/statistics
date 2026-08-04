<?php

use AndiLeni\Statistics\ReportPdfGenerator;
use DateTimeImmutable;

$addon = rex_addon::get('statistics');
$pdfoutAvailable = rex_addon::get('pdfout')->isAvailable() && class_exists('FriendsOfRedaxo\\PdfOut\\PdfOut');

$assetVersion = rawurlencode((string) $addon->getVersion());
rex_view::addCssFile($addon->getAssetsUrl('reports.css') . '?v=' . $assetVersion);
rex_view::addJsFile($addon->getAssetsUrl('reports.js') . '?v=' . $assetVersion);

$message = '';
$periodType = rex_request('period_type', 'string', 'month');
$periodMonth = rex_request('period_month', 'string', date('Y-m'));
$periodWeek = rex_request('period_week', 'string', date('o-\\WW'));
$periodYear = rex_request('period_year', 'int', (int) date('Y') - 1);

if (!in_array($periodType, ['week', 'month', 'year'], true)) {
    $periodType = 'month';
}

if (rex_request_method() === 'post' && rex_post('func', 'string', '') === 'generate_report') {
    $token = rex_csrf_token::factory('statistics_report_generate');
    if (!$token->isValid()) {
        $message .= rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (!$pdfoutAvailable) {
        $message .= rex_view::error($addon->i18n('statistics_report_pdfout_missing'));
    } else {
        $periodValue = $periodMonth;
        if ('week' === $periodType) {
            $periodValue = $periodWeek;
        } elseif ('year' === $periodType) {
            $periodValue = (string) $periodYear;
        }

        try {
            $generator = new ReportPdfGenerator();
            $generator->generate($periodType, $periodValue);
        } catch (Throwable $exception) {
            rex_logger::logException($exception);
            $message .= rex_view::error($addon->i18n('statistics_report_generate_error') . '<br>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES));
        }
    }
}

if (!$pdfoutAvailable) {
    $message .= rex_view::warning($addon->i18n('statistics_report_pdfout_missing'));
}

echo $message;

$lastWeek = (new DateTimeImmutable('monday last week'))->format('o-\\WW');
$lastMonth = (new DateTimeImmutable('first day of last month'))->format('Y-m');
$lastYear = (string) ((int) date('Y') - 1);
$waitStatus1 = htmlspecialchars($addon->i18n('statistics_report_wait_status_1'), ENT_QUOTES);
$waitStatus2 = htmlspecialchars($addon->i18n('statistics_report_wait_status_2'), ENT_QUOTES);
$waitStatus3 = htmlspecialchars($addon->i18n('statistics_report_wait_status_3'), ENT_QUOTES);
$waitStatus4 = htmlspecialchars($addon->i18n('statistics_report_wait_status_4'), ENT_QUOTES);

$formHtml = '';
$formHtml .= '<div id="statistics-report-root" class="statistics-report" data-wait-status-1="' . $waitStatus1 . '" data-wait-status-2="' . $waitStatus2 . '" data-wait-status-3="' . $waitStatus3 . '" data-wait-status-4="' . $waitStatus4 . '">';
$formHtml .= '<div class="statistics-report__hero">';
$formHtml .= '<div class="statistics-report__hero-icon"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></div>';
$formHtml .= '<div class="statistics-report__hero-content">';
$formHtml .= '<h3>' . htmlspecialchars($addon->i18n('statistics_report_title'), ENT_QUOTES) . '</h3>';
$formHtml .= '<p>' . htmlspecialchars($addon->i18n('statistics_report_description'), ENT_QUOTES) . '</p>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="statistics-report__quick">';
$formHtml .= '<p class="statistics-report__quick-label"><i class="fa fa-magic" aria-hidden="true"></i> ' . htmlspecialchars($addon->i18n('statistics_report_quick_title'), ENT_QUOTES) . '</p>';
$formHtml .= '<div class="statistics-report__quick-buttons">';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_week" data-week-value="' . htmlspecialchars($lastWeek, ENT_QUOTES) . '">' . htmlspecialchars($addon->i18n('statistics_report_quick_last_week'), ENT_QUOTES) . '</button>';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_month" data-month-value="' . htmlspecialchars($lastMonth, ENT_QUOTES) . '">' . htmlspecialchars($addon->i18n('statistics_report_quick_last_month'), ENT_QUOTES) . '</button>';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_year" data-year-value="' . htmlspecialchars($lastYear, ENT_QUOTES) . '">' . htmlspecialchars($addon->i18n('statistics_report_quick_last_year'), ENT_QUOTES) . '</button>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<form action="' . htmlspecialchars(rex_url::currentBackendPage(), ENT_QUOTES) . '" method="post" data-report-form>';
$formHtml .= rex_csrf_token::factory('statistics_report_generate')->getHiddenField();
$formHtml .= '<input type="hidden" name="func" value="generate_report">';

$formHtml .= '<div class="form-group statistics-report__type">';
$formHtml .= '<label>' . htmlspecialchars($addon->i18n('statistics_report_period_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<div class="statistics-report__type-grid">';
$formHtml .= '<label class="statistics-report__type-card' . ('week' === $periodType ? ' is-active' : '') . '">';
$formHtml .= '<input type="radio" name="period_type" value="week"' . ('week' === $periodType ? ' checked' : '') . '>';
$formHtml .= '<i class="fa fa-calendar-o" aria-hidden="true"></i>';
$formHtml .= '<span>' . htmlspecialchars($addon->i18n('statistics_report_period_week'), ENT_QUOTES) . '</span>';
$formHtml .= '</label>';
$formHtml .= '<label class="statistics-report__type-card' . ('month' === $periodType ? ' is-active' : '') . '">';
$formHtml .= '<input type="radio" name="period_type" value="month"' . ('month' === $periodType ? ' checked' : '') . '>';
$formHtml .= '<i class="fa fa-calendar" aria-hidden="true"></i>';
$formHtml .= '<span>' . htmlspecialchars($addon->i18n('statistics_report_period_month'), ENT_QUOTES) . '</span>';
$formHtml .= '</label>';
$formHtml .= '<label class="statistics-report__type-card' . ('year' === $periodType ? ' is-active' : '') . '">';
$formHtml .= '<input type="radio" name="period_type" value="year"' . ('year' === $periodType ? ' checked' : '') . '>';
$formHtml .= '<i class="fa fa-calendar-check-o" aria-hidden="true"></i>';
$formHtml .= '<span>' . htmlspecialchars($addon->i18n('statistics_report_period_year'), ENT_QUOTES) . '</span>';
$formHtml .= '</label>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="row statistics-report__inputs">';
$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('week' === $periodType ? ' is-active' : '') . '" data-period-panel="week">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-week">' . htmlspecialchars($addon->i18n('statistics_report_week_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-week" type="week" class="form-control" name="period_week" value="' . htmlspecialchars($periodWeek, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('month' === $periodType ? ' is-active' : '') . '" data-period-panel="month">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-month">' . htmlspecialchars($addon->i18n('statistics_report_month_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-month" type="month" class="form-control" name="period_month" value="' . htmlspecialchars($periodMonth, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('year' === $periodType ? ' is-active' : '') . '" data-period-panel="year">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-year">' . htmlspecialchars($addon->i18n('statistics_report_year_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-year" type="number" min="2000" max="2100" class="form-control" name="period_year" value="' . htmlspecialchars((string) $periodYear, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="alert alert-info statistics-report__runtime-hint">';
$formHtml .= '<i class="fa fa-info-circle" aria-hidden="true"></i> ' . htmlspecialchars($addon->i18n('statistics_report_runtime_hint'), ENT_QUOTES);
$formHtml .= '</div>';

$formHtml .= '<div class="statistics-report__wait" data-report-wait aria-live="polite">';
$formHtml .= '<div class="statistics-report__wait-spinner" aria-hidden="true"></div>';
$formHtml .= '<div class="statistics-report__wait-content">';
$formHtml .= '<strong>' . htmlspecialchars($addon->i18n('statistics_report_wait_title'), ENT_QUOTES) . '</strong>';
$formHtml .= '<p data-report-status-text>' . htmlspecialchars($addon->i18n('statistics_report_wait_status_1'), ENT_QUOTES) . '</p>';
$formHtml .= '<div class="statistics-report__progress">';
$formHtml .= '<div class="statistics-report__progress-bar" data-report-progress-bar style="width: 8%"></div>';
$formHtml .= '</div>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<button class="btn btn-primary statistics-report__submit" data-report-submit type="submit"' . (!$pdfoutAvailable ? ' disabled' : '') . '>';
$formHtml .= '<i class="fa fa-file-pdf-o" aria-hidden="true"></i> ' . htmlspecialchars($addon->i18n('statistics_report_generate_button'), ENT_QUOTES);
$formHtml .= '</button>';
$formHtml .= '</form>';
$formHtml .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('statistics_report_panel_title'), false);
$fragment->setVar('body', $formHtml, false);
echo $fragment->parse('core/page/section.php');
