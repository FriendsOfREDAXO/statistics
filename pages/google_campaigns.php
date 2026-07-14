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

$requestOnlyAds = rex_request('only_ads', 'boolean', false);

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

$extractCampaignData = static function (string $url) use ($trackedParams, $addon): ?array {
    $trimmed = trim($url);
    if ('' === $trimmed) {
        return null;
    }

    $parseTarget = $trimmed;
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $parseTarget)) {
        if (str_starts_with($parseTarget, '//')) {
            $parseTarget = 'https:' . $parseTarget;
        } elseif (str_starts_with($parseTarget, '/')) {
            $parseTarget = 'https://example.invalid' . $parseTarget;
        } else {
            $parseTarget = 'https://' . $parseTarget;
        }
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

        $normalized[$normalizedKey] = is_array($value)
            ? trim((string) reset($value))
            : trim((string) $value);
    }

    if ([] === $normalized) {
        return null;
    }

    $landingPath = is_string($path) && '' !== $path ? $path : '/';

    $campaignId = (string) ($normalized['gad_campaignid'] ?? '');
    $utmCampaign = (string) ($normalized['utm_campaign'] ?? '');
    $utmId = (string) ($normalized['utm_id'] ?? '');
    $hasClickId = (isset($normalized['gclid']) && '' !== $normalized['gclid'])
        || (isset($normalized['gbraid']) && '' !== $normalized['gbraid'])
        || (isset($normalized['wbraid']) && '' !== $normalized['wbraid']);

    if ('' === $campaignId && '' === $utmCampaign && '' === $utmId && !$hasClickId) {
        return null;
    }

    if ('' !== $campaignId) {
        $campaignType = 'google_ads';
        $campaignLabel = 'Google Ads #' . $campaignId;
        $groupKey = 'ads:' . $campaignId . '|' . $landingPath;
    } elseif ('' !== $utmCampaign) {
        $campaignType = 'utm_campaign';
        $campaignLabel = 'UTM ' . $utmCampaign;
        $groupKey = 'utm_campaign:' . $utmCampaign . '|' . $landingPath;
    } elseif ('' !== $utmId) {
        $campaignType = 'utm_id';
        $campaignLabel = 'UTM-ID ' . $utmId;
        $groupKey = 'utm_id:' . $utmId . '|' . $landingPath;
    } else {
        $campaignType = 'click_id_only';
        $campaignLabel = $addon->i18n('statistics_google_campaigns_click_id_only');
        $groupKey = 'click_only|' . $landingPath;
    }

    return [
        'signature' => $groupKey,
        'campaign_id' => $campaignId,
        'campaign_label' => $campaignLabel,
        'campaign_type' => $campaignType,
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
$totalDetectedCalls = 0;
$totalAdsCalls = 0;
$campaignIds = [];

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

    if ($requestOnlyAds && '' === $campaignData['campaign_id']) {
        continue;
    }

    $signature = $campaignData['signature'];
    if (!isset($groups[$signature])) {
        $groups[$signature] = [
            'campaign_id' => $campaignData['campaign_id'],
            'campaign_label' => $campaignData['campaign_label'],
            'campaign_type' => $campaignData['campaign_type'],
            'landing_path' => $campaignData['landing_path'],
            'count' => 0,
            'click_ids' => [],
            'param_keys' => [],
            'urls' => [],
        ];
    }

    $groups[$signature]['count'] += $count;
    $totalDetectedCalls += $count;

    if ('' !== $campaignData['campaign_id']) {
        $totalAdsCalls += $count;
        $campaignIds[$campaignData['campaign_id']] = true;
    }

    foreach (['gclid', 'gbraid', 'wbraid'] as $clickIdKey) {
        if (isset($campaignData['params'][$clickIdKey]) && '' !== $campaignData['params'][$clickIdKey]) {
            $groups[$signature]['click_ids'][$clickIdKey . ':' . $campaignData['params'][$clickIdKey]] = true;
        }
    }

    foreach ($campaignData['params'] as $paramKey => $paramValue) {
        if ('' !== $paramValue) {
            $groups[$signature]['param_keys'][$paramKey] = true;
        }
    }

    $groups[$signature]['urls'][] = [
        'url' => $url,
        'count' => $count,
    ];
}

if ([] === $groups) {
    $body = '<div class="alert alert-warning" style="margin-bottom:10px;">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_no_hits'), ENT_QUOTES) . '</div>';
    $body .= '<p><a class="btn btn-default" href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_open_ads'), ENT_QUOTES) . '</a></p>';
    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), $body . rex_view::info($addon->i18n('statistics_no_data')));
    return;
}

uasort($groups, static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

$topGroup = reset($groups);
$topGroupText = '';
if (is_array($topGroup)) {
    $topGroupText = (string) $topGroup['campaign_label'] . ' - ' . (string) $topGroup['landing_path'];
}

$statusClass = $totalAdsCalls > 0 ? 'alert alert-success' : 'alert alert-warning';
$statusText = $totalAdsCalls > 0
    ? sprintf($addon->i18n('statistics_google_campaigns_status_found'), (string) $totalAdsCalls)
    : $addon->i18n('statistics_google_campaigns_status_only_click_ids');

$filterBase = [
    'page' => 'statistics/google_campaigns',
    'date_start' => $filterDateHelper->date_start->format('Y-m-d'),
    'date_end' => $filterDateHelper->date_end->format('Y-m-d'),
];
$allLink = rex_url::backendController(array_merge($filterBase, ['only_ads' => 0]), false);
$onlyAdsLink = rex_url::backendController(array_merge($filterBase, ['only_ads' => 1]), false);

$intro = '<div class="' . $statusClass . '" style="margin-bottom:10px;">' . htmlspecialchars($statusText, ENT_QUOTES) . '</div>';
$intro .= '<div class="alert alert-info" style="margin-bottom:10px;">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_intro'), ENT_QUOTES) . ' '
    . '<a href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_open_ads'), ENT_QUOTES) . '</a>'
    . '</div>';

$kpi = '<div class="row">';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_kpi_detected_calls'), ENT_QUOTES) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) $totalDetectedCalls, ENT_QUOTES) . '</div></div></div></div>';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_kpi_ads_campaigns'), ENT_QUOTES) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . htmlspecialchars((string) count($campaignIds), ENT_QUOTES) . '</div></div></div></div>';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_kpi_top_group'), ENT_QUOTES) . '</div><div style="font-size:15px;font-weight:700;line-height:1.3;word-break:break-word;">' . htmlspecialchars($topGroupText, ENT_QUOTES) . '</div></div></div></div>';
$kpi .= '</div>';

$buttons = '<div style="margin-bottom:10px;">';
$buttons .= '<a class="btn btn-primary" href="' . htmlspecialchars($allLink, ENT_QUOTES) . '">' . htmlspecialchars($addon->i18n('statistics_filter_all'), ENT_QUOTES) . '</a> ';
$buttons .= '<a class="btn btn-primary" href="' . htmlspecialchars($onlyAdsLink, ENT_QUOTES) . '">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_only_ads'), ENT_QUOTES) . '</a>';
$buttons .= '</div>';

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
        'only_ads' => $requestOnlyAds ? 1 : 0,
    ], false);

    $paramKeys = array_keys($group['param_keys']);
    sort($paramKeys);
    $paramsText = implode(', ', $paramKeys);

    $table .= '<tr>';
    $table .= '<td><a href="' . htmlspecialchars($campaignDetailUrl, ENT_QUOTES) . '">' . htmlspecialchars((string) $group['campaign_label'], ENT_QUOTES) . '</a></td>';
    $table .= '<td>' . htmlspecialchars((string) $group['landing_path'], ENT_QUOTES) . '</td>';
    $table .= '<td data-sort="' . htmlspecialchars((string) $group['count'], ENT_QUOTES) . '">' . htmlspecialchars((string) $group['count'], ENT_QUOTES) . '</td>';
    $table .= '<td data-sort="' . htmlspecialchars((string) count($group['click_ids']), ENT_QUOTES) . '">' . htmlspecialchars((string) count($group['click_ids']), ENT_QUOTES) . '</td>';
    $table .= '<td title="' . htmlspecialchars($paramsText, ENT_QUOTES) . '">' . htmlspecialchars($paramsText, ENT_QUOTES) . '</td>';
    $table .= '</tr>';
}

$table .= '</tbody></table>';

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), $intro . $kpi . $buttons . $table);

if ('' !== $requestCampaignKey) {
    $decodedSignature = base64_decode($requestCampaignKey, true);
    if (is_string($decodedSignature) && isset($groups[$decodedSignature])) {
        $selected = $groups[$decodedSignature];
        usort($selected['urls'], static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

        $detailBody = '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($selected['campaign_label'], ENT_QUOTES) . '</strong> - ' . htmlspecialchars($selected['landing_path'], ENT_QUOTES) . '</div>';
        $detailBody .= '<p><a class="btn btn-default" href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($addon->i18n('statistics_google_campaigns_open_ads'), ENT_QUOTES) . '</a></p>';
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
