<?php

use AndiLeni\Statistics\ReportPdfGenerator;

$addon = rex_addon::get('statistics');
$pdfoutAvailable = rex_addon::get('pdfout')->isAvailable() && class_exists('FriendsOfRedaxo\\PdfOut\\PdfOut');

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
            $message .= rex_view::error($addon->i18n('statistics_report_generate_error') . '<br>' . rex_escape($exception->getMessage()));
        }
    }
}

if (!$pdfoutAvailable) {
    $message .= rex_view::warning($addon->i18n('statistics_report_pdfout_missing'));
}

echo $message;

$lastWeek = (new \DateTimeImmutable('monday last week'))->format('o-\\WW');
$lastMonth = (new \DateTimeImmutable('first day of last month'))->format('Y-m');
$lastYear = (string) ((int) date('Y') - 1);
$waitStatus1 = rex_escape($addon->i18n('statistics_report_wait_status_1'));
$waitStatus2 = rex_escape($addon->i18n('statistics_report_wait_status_2'));
$waitStatus3 = rex_escape($addon->i18n('statistics_report_wait_status_3'));
$waitStatus4 = rex_escape($addon->i18n('statistics_report_wait_status_4'));
$waitButtonLabel = rex_escape($addon->i18n('statistics_report_wait_button'));

$formHtml = '';
$formHtml .= '<div id="statistics-report-root" class="statistics-report" data-wait-status-1="' . $waitStatus1 . '" data-wait-status-2="' . $waitStatus2 . '" data-wait-status-3="' . $waitStatus3 . '" data-wait-status-4="' . $waitStatus4 . '" data-wait-button-label="' . $waitButtonLabel . '">';
$formHtml .= '<h3 class="statistics-report__title"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> ' . rex_escape($addon->i18n('statistics_report_title')) . '</h3>';
$formHtml .= '<p class="statistics-report__intro">' . rex_escape($addon->i18n('statistics_report_description')) . '</p>';

$formHtml .= '<div class="statistics-report__quick">';
$formHtml .= '<p class="statistics-report__step">1. ' . rex_escape($addon->i18n('statistics_report_quick_title')) . '</p>';
$formHtml .= '<div class="statistics-report__quick-buttons">';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_week" data-week-value="' . rex_escape($lastWeek) . '">' . rex_escape($addon->i18n('statistics_report_quick_last_week')) . '</button>';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_month" data-month-value="' . rex_escape($lastMonth) . '">' . rex_escape($addon->i18n('statistics_report_quick_last_month')) . '</button>';
$formHtml .= '<button type="button" class="btn btn-default" data-report-quick="last_year" data-year-value="' . rex_escape($lastYear) . '">' . rex_escape($addon->i18n('statistics_report_quick_last_year')) . '</button>';
$formHtml .= '</div>';
$formHtml .= '<p class="help-block">' . rex_escape($addon->i18n('statistics_report_quick_autostart_note')) . '</p>';
$formHtml .= '</div>';

$formHtml .= '<form action="' . rex_escape(rex_url::currentBackendPage()) . '" method="post" data-report-form>';
$formHtml .= rex_csrf_token::factory('statistics_report_generate')->getHiddenField();
$formHtml .= '<input type="hidden" name="func" value="generate_report">';

$formHtml .= '<div class="form-group statistics-report__type">';
$formHtml .= '<p class="statistics-report__step">2. ' . rex_escape($addon->i18n('statistics_report_period_label')) . '</p>';
$formHtml .= '<div class="statistics-report__radios">';
$formHtml .= '<label class="radio-inline"><input type="radio" name="period_type" value="week"' . ('week' === $periodType ? ' checked' : '') . '> ' . rex_escape($addon->i18n('statistics_report_period_week')) . '</label>';
$formHtml .= '<label class="radio-inline"><input type="radio" name="period_type" value="month"' . ('month' === $periodType ? ' checked' : '') . '> ' . rex_escape($addon->i18n('statistics_report_period_month')) . '</label>';
$formHtml .= '<label class="radio-inline"><input type="radio" name="period_type" value="year"' . ('year' === $periodType ? ' checked' : '') . '> ' . rex_escape($addon->i18n('statistics_report_period_year')) . '</label>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="row statistics-report__inputs">';
$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('week' === $periodType ? ' is-active' : '') . '" data-period-panel="week">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-week">' . rex_escape($addon->i18n('statistics_report_week_label')) . '</label>';
$formHtml .= '<input id="statistics-report-period-week" type="week" class="form-control" name="period_week" value="' . rex_escape($periodWeek) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('month' === $periodType ? ' is-active' : '') . '" data-period-panel="month">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-month">' . rex_escape($addon->i18n('statistics_report_month_label')) . '</label>';
$formHtml .= '<input id="statistics-report-period-month" type="month" class="form-control" name="period_month" value="' . rex_escape($periodMonth) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4 statistics-report__panel' . ('year' === $periodType ? ' is-active' : '') . '" data-period-panel="year">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-year">' . rex_escape($addon->i18n('statistics_report_year_label')) . '</label>';
$formHtml .= '<input id="statistics-report-period-year" type="number" min="2000" max="2100" class="form-control" name="period_year" value="' . rex_escape((string) $periodYear) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="alert alert-info statistics-report__runtime-hint">';
$formHtml .= '<i class="fa fa-info-circle" aria-hidden="true"></i> ' . rex_escape($addon->i18n('statistics_report_runtime_hint'));
$formHtml .= '</div>';

$formHtml .= '<div class="statistics-report__wait" data-report-wait aria-live="polite" style="display:none">';
$formHtml .= '<div class="statistics-report__wait-spinner" aria-hidden="true"></div>';
$formHtml .= '<div class="statistics-report__wait-content">';
$formHtml .= '<strong>' . rex_escape($addon->i18n('statistics_report_wait_title')) . '</strong>';
$formHtml .= '<p data-report-status-text>' . rex_escape($addon->i18n('statistics_report_wait_status_1')) . '</p>';
$formHtml .= '<div class="statistics-report__progress">';
$formHtml .= '<div class="statistics-report__progress-bar" data-report-progress-bar style="width: 8%"></div>';
$formHtml .= '</div>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<p class="statistics-report__step">3. ' . rex_escape($addon->i18n('statistics_report_generate_button')) . '</p>';
$formHtml .= '<button class="btn btn-primary statistics-report__submit" data-report-submit type="submit"' . (!$pdfoutAvailable ? ' disabled' : '') . '>';
$formHtml .= '<i class="fa fa-file-pdf-o" aria-hidden="true"></i> ' . rex_escape($addon->i18n('statistics_report_generate_button'));
$formHtml .= '</button>';
$formHtml .= '</form>';
$formHtml .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('statistics_report_panel_title'), false);
$fragment->setVar('body', $formHtml, false);
echo $fragment->parse('core/page/section.php');
