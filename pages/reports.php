<?php

use AndiLeni\Statistics\ReportPdfGenerator;

$addon = rex_addon::get('statistics');
$pdfoutAvailable = rex_addon::get('pdfout')->isAvailable() && class_exists('FriendsOfRedaxo\\PdfOut\\PdfOut');

$message = '';
$periodType = rex_request('period_type', 'string', 'month');
$periodMonth = rex_request('period_month', 'string', date('Y-m'));
$periodWeek = rex_request('period_week', 'string', date('o-\\WW'));
$periodYear = rex_request('period_year', 'int', (int) date('Y'));

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

$formHtml = '';
$formHtml .= '<form action="' . htmlspecialchars(rex_url::currentBackendPage(), ENT_QUOTES) . '" method="post" style="max-width:860px">';
$formHtml .= rex_csrf_token::factory('statistics_report_generate')->getHiddenField();
$formHtml .= '<input type="hidden" name="func" value="generate_report">';

$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-type">' . htmlspecialchars($addon->i18n('statistics_report_period_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<select id="statistics-report-period-type" class="form-control" name="period_type">';
$formHtml .= '<option value="week"' . ('week' === $periodType ? ' selected' : '') . '>' . htmlspecialchars($addon->i18n('statistics_report_period_week'), ENT_QUOTES) . '</option>';
$formHtml .= '<option value="month"' . ('month' === $periodType ? ' selected' : '') . '>' . htmlspecialchars($addon->i18n('statistics_report_period_month'), ENT_QUOTES) . '</option>';
$formHtml .= '<option value="year"' . ('year' === $periodType ? ' selected' : '') . '>' . htmlspecialchars($addon->i18n('statistics_report_period_year'), ENT_QUOTES) . '</option>';
$formHtml .= '</select>';
$formHtml .= '</div>';

$formHtml .= '<div class="row">';
$formHtml .= '<div class="col-md-4">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-week">' . htmlspecialchars($addon->i18n('statistics_report_week_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-week" type="week" class="form-control" name="period_week" value="' . htmlspecialchars($periodWeek, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-month">' . htmlspecialchars($addon->i18n('statistics_report_month_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-month" type="month" class="form-control" name="period_month" value="' . htmlspecialchars($periodMonth, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<div class="col-md-4">';
$formHtml .= '<div class="form-group">';
$formHtml .= '<label for="statistics-report-period-year">' . htmlspecialchars($addon->i18n('statistics_report_year_label'), ENT_QUOTES) . '</label>';
$formHtml .= '<input id="statistics-report-period-year" type="number" min="2000" max="2100" class="form-control" name="period_year" value="' . htmlspecialchars((string) $periodYear, ENT_QUOTES) . '">';
$formHtml .= '</div>';
$formHtml .= '</div>';
$formHtml .= '</div>';

$formHtml .= '<p class="help-block">' . htmlspecialchars($addon->i18n('statistics_report_description'), ENT_QUOTES) . '</p>';
$formHtml .= '<button class="btn btn-primary" type="submit"' . (!$pdfoutAvailable ? ' disabled' : '') . '>' . htmlspecialchars($addon->i18n('statistics_report_generate_button'), ENT_QUOTES) . '</button>';
$formHtml .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('statistics_report_title'), false);
$fragment->setVar('body', $formHtml, false);
echo $fragment->parse('core/page/section.php');
