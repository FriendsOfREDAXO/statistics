<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

$currentBackendPage = rex_get('page', 'string', '');
$requestDateStart = htmlspecialchars_decode(rex_request('date_start', 'string', ''));
$requestDateEnd = htmlspecialchars_decode(rex_request('date_end', 'string', ''));
$requestCampaignKey = rex_request('campaign_key', 'string', '');

$filterDateHelper = new DateFilter($requestDateStart, $requestDateEnd, 'pagestats_visits_per_url');
echo StatsSubpageRenderer::renderFilter($currentBackendPage, $filterDateHelper);

$trackedParams = [
    'gad_campaignid',
    'gad_source',
    'gclid',
    'gbraid',
    'wbraid',
    'utm_id',
    'utm_campaign',
    'utm_source',
    'utm_medium',
    'utm_term',
    'utm_content',
];

$extractCampaignData = static function (string $url) use ($trackedParams): ?array {
    $trimmed = trim($url);
    if ('' === $trimmed) {
        return null;
    }

    $parseTarget = $trimmed;
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $parseTarget)) {
        $parseTarget = 'https://' . ltrim($parseTarget, '/');
    }

    $path = parse_url($parseTarget, PHP_URL_PATH);
    $query = parse_url($parseTarget, PHP_URL_QUERY);

    if (!is_string($query) || '' === $query) {
        return null;
    }

    $params = [];
    parse_str($query, $params);
    if ([] === $params) {
        return null;
    }

    $normalized = [];
    foreach ($params as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        $normalizedKey = strtolower($key);
        if (!in_array($normalizedKey, $trackedParams, true)) {
            continue;
        }

        if (is_array($value)) {
            $normalized[$normalizedKey] = trim((string) reset($value));
        } else {
            $normalized[$normalizedKey] = trim((string) $value);
        }
    }

    if ([] === $normalized) {
        return null;
    }

    $landingPath = is_string($path) && '' !== $path ? $path : '/';

    $campaignLabel = '';
    $campaignKey = '';

    if (isset($normalized['gad_campaignid']) && '' !== $normalized['gad_campaignid']) {
        $campaignKey = 'gad_campaignid:' . $normalized['gad_campaignid'];
        $campaignLabel = 'Google Ads #' . $normalized['gad_campaignid'];
    } elseif (isset($normalized['utm_id']) && '' !== $normalized['utm_id']) {
        $campaignKey = 'utm_id:' . $normalized['utm_id'];
        $campaignLabel = 'UTM-ID ' . $normalized['utm_id'];
    } elseif (isset($normalized['utm_campaign']) && '' !== $normalized['utm_campaign']) {
        $campaignKey = 'utm_campaign:' . $normalized['utm_campaign'];
        $campaignLabel = 'UTM ' . $normalized['utm_campaign'];
    } elseif (isset($normalized['gbraid']) && '' !== $normalized['gbraid']) {
        $campaignKey = 'gbraid:' . $normalized['gbraid'];
        $campaignLabel = 'GBRAID';
    } elseif (isset($normalized['wbraid']) && '' !== $normalized['wbraid']) {
        $campaignKey = 'wbraid:' . $normalized['wbraid'];
        $campaignLabel = 'WBRAID';
    } elseif (isset($normalized['gclid']) && '' !== $normalized['gclid']) {
        $campaignKey = 'gclid:' . $normalized['gclid'];
        $campaignLabel = 'GCLID';
    } else {
        return null;
    }

    return [
        'signature' => $campaignKey . '|' . $landingPath,
        'campaign_key' => $campaignKey,
        'campaign_label' => $campaignLabel,
        'landing_path' => $landingPath,
        'params' => $normalized,
    ];
};

$sql = rex_sql::factory();
$urlRows = $sql->getArray(
    'SELECT url, SUM(count) AS count FROM ' . rex::getTable('pagestats_visits_per_url')
    . ' WHERE date BETWEEN :start AND :end GROUP BY url ORDER BY count DESC LIMIT 3000',
    [
        'start' => $filterDateHelper->date_start->format('Y-m-d'),
        'end' => $filterDateHelper->date_end->format('Y-m-d'),
    ]
);

$groups = [];

foreach ($urlRows as $row) {
    $url = (string) ($row['url'] ?? '');
    $count = (int) ($row['count'] ?? 0);

    if ($count <= 0) {
        continue;
    }

    $campaignData = $extractCampaignData($url);
    if (null === $campaignData) {
        continue;
    }

    $signature = $campaignData['signature'];
    if (!isset($groups[$signature])) {
        $groups[$signature] = [
            'campaign_key' => $campaignData['campaign_key'],
            'campaign_label' => $campaignData['campaign_label'],
            'landing_path' => $campaignData['landing_path'],
            'count' => 0,
            'click_ids' => [],
            'params' => $campaignData['params'],
            'urls' => [],
        ];
    }

    $groups[$signature]['count'] += $count;

    foreach (['gclid', 'gbraid', 'wbraid'] as $clickIdKey) {
        if (isset($campaignData['params'][$clickIdKey]) && '' !== $campaignData['params'][$clickIdKey]) {
            $groups[$signature]['click_ids'][$clickIdKey . ':' . $campaignData['params'][$clickIdKey]] = true;
        }
    }

    $groups[$signature]['urls'][] = [
        'url' => $url,
        'count' => $count,
    ];
}

if ([] === $groups) {
    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), rex_view::info($addon->i18n('statistics_no_data')));
    return;
}

uasort($groups, static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

$intro = '<div class="alert alert-info" style="margin-bottom:10px;">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_intro'), ENT_QUOTES) . '</div>';

$table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table" data-page-length="30">';
$table .= '<thead><tr>';
$table .= '<th>' . htmlspecialchars($addon->i18n('statistics_google_campaigns_group'), ENT_QUOTES) . '</th>';
$table .= '<th>' . htmlspecialchars($addon->i18n('statistics_google_campaigns_landing'), ENT_QUOTES) . '</th>';
$table .= '<th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
$table .= '<th>' . htmlspecialchars($addon->i18n('statistics_google_campaigns_click_ids'), ENT_QUOTES) . '</th>';
$table .= '<th>' . htmlspecialchars($addon->i18n('statistics_google_campaigns_params'), ENT_QUOTES) . '</th>';
$table .= '</tr></thead><tbody>';

foreach ($groups as $signature => $group) {
    $campaignDetailUrl = rex_url::backendController([
        'page' => 'statistics/google_campaigns',
        'campaign_key' => base64_encode((string) $signature),
        'date_start' => $filterDateHelper->date_start->format('Y-m-d'),
        'date_end' => $filterDateHelper->date_end->format('Y-m-d'),
    ], false);

    $paramPairs = [];
    foreach ($group['params'] as $k => $v) {
        if ('' === (string) $v) {
            continue;
        }
        $paramPairs[] = $k . '=' . $v;
    }

    $paramsText = implode(', ', $paramPairs);

    $table .= '<tr>';
    $table .= '<td><a href="' . htmlspecialchars($campaignDetailUrl, ENT_QUOTES) . '">' . htmlspecialchars((string) $group['campaign_label'], ENT_QUOTES) . '</a></td>';
    $table .= '<td>' . htmlspecialchars((string) $group['landing_path'], ENT_QUOTES) . '</td>';
    $table .= '<td data-sort="' . htmlspecialchars((string) $group['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $group['count'], ENT_QUOTES) . '</td>';
    $table .= '<td data-sort="' . htmlspecialchars((string) count($group['click_ids']), ENT_QUOTES) . '">' . htmlspecialchars((string) count($group['click_ids']), ENT_QUOTES) . '</td>';
    $table .= '<td title="' . htmlspecialchars($paramsText, ENT_QUOTES) . '">' . htmlspecialchars($paramsText, ENT_QUOTES) . '</td>';
    $table .= '</tr>';
}

$table .= '</tbody></table>';

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), $intro . $table);

if ('' !== $requestCampaignKey) {
    $decodedSignature = base64_decode($requestCampaignKey, true);
    if (is_string($decodedSignature) && isset($groups[$decodedSignature])) {
        $selected = $groups[$decodedSignature];
        usort($selected['urls'], static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

        $detailBody = '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($selected['campaign_label'], ENT_QUOTES) . '</strong> - ' . htmlspecialchars($selected['landing_path'], ENT_QUOTES) . '</div>';
        $detailBody .= '<table class="table table-striped table-hover table-bordered"><thead><tr>';
        $detailBody .= '<th>' . htmlspecialchars($addon->i18n('statistics_url'), ENT_QUOTES) . '</th>';
        $detailBody .= '<th>' . htmlspecialchars($addon->i18n('statistics_count'), ENT_QUOTES) . '</th>';
        $detailBody .= '</tr></thead><tbody>';

        foreach ($selected['urls'] as $urlEntry) {
            $detailBody .= '<tr>';
            $detailBody .= '<td>' . htmlspecialchars((string) $urlEntry['url'], ENT_QUOTES) . '</td>';
            $detailBody .= '<td data-sort="' . htmlspecialchars((string) $urlEntry['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $urlEntry['count'], ENT_QUOTES) . '</td>';
            $detailBody .= '</tr>';
        }

        $detailBody .= '</tbody></table>';

        echo StatsSubpageRenderer::renderInfoSection(
            $addon->i18n('statistics_google_campaigns_detail'),
            $selected['campaign_label'] . ' - ' . $selected['landing_path'],
            $detailBody
        );
    }
}
