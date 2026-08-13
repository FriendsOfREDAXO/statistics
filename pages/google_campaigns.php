<?php

use AndiLeni\Statistics\DateFilter;
use AndiLeni\Statistics\StatsSubpageRenderer;

$addon = rex_addon::get('statistics');

if (!(bool) $addon->getConfig('statistics_google_campaigns_enable', false)) {
    echo rex_view::warning($addon->i18n('statistics_google_campaigns_disabled'));
    return;
}

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
        $campaignLabel = sprintf($addon->i18n('statistics_google_campaigns_label_ads'), $campaignId);
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
    $body = '<div class="alert alert-warning" style="margin-bottom:10px;">' . rex_escape($addon->i18n('statistics_google_campaigns_no_hits')) . '</div>';
    $body .= '<p><a class="btn btn-default" href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . rex_escape($addon->i18n('statistics_google_campaigns_open_ads')) . '</a></p>';
    echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), $body . rex_view::info($addon->i18n('statistics_no_data')));
    return;
}

uasort($groups, static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

$topGroup = reset($groups);
$topGroupText = (string) $topGroup['campaign_label'] . ' - ' . (string) $topGroup['landing_path'];

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

$intro = '<div class="' . $statusClass . '" style="margin-bottom:10px;">' . rex_escape($statusText) . '</div>';
$intro .= '<div class="alert alert-info" style="margin-bottom:10px;">' . rex_escape($addon->i18n('statistics_google_campaigns_intro')) . ' '
    . '<a href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . rex_escape($addon->i18n('statistics_google_campaigns_open_ads')) . '</a>'
    . '</div>';

$kpi = '<div class="row">';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . rex_escape($addon->i18n('statistics_google_campaigns_kpi_detected_calls')) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . rex_escape((string) $totalDetectedCalls) . '</div></div></div></div>';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . rex_escape($addon->i18n('statistics_google_campaigns_kpi_ads_campaigns')) . '</div><div style="font-size:28px;font-weight:700;line-height:1.2;">' . rex_escape((string) count($campaignIds)) . '</div></div></div></div>';
$kpi .= '<div class="col-sm-4"><div class="panel panel-default"><div class="panel-body"><div class="text-muted">' . rex_escape($addon->i18n('statistics_google_campaigns_kpi_top_group')) . '</div><div style="font-size:15px;font-weight:700;line-height:1.3;word-break:break-word;">' . rex_escape($topGroupText) . '</div></div></div></div>';
$kpi .= '</div>';

$buttons = '<div style="margin-bottom:10px;">';
$buttons .= '<a class="btn btn-primary" href="' . rex_escape($allLink) . '">' . rex_escape($addon->i18n('statistics_filter_all')) . '</a> ';
$buttons .= '<a class="btn btn-primary" href="' . rex_escape($onlyAdsLink) . '">' . rex_escape($addon->i18n('statistics_google_campaigns_only_ads')) . '</a>';
$buttons .= '</div>';

$table = '<table class="table-bordered dt_order_second statistics_table table-striped table-hover table" data-page-length="30">';
$table .= '<thead><tr>';
$table .= '<th>' . rex_escape($addon->i18n('statistics_google_campaigns_group')) . '</th>';
$table .= '<th>' . rex_escape($addon->i18n('statistics_google_campaigns_landing')) . '</th>';
$table .= '<th>' . rex_escape($addon->i18n('statistics_count')) . '</th>';
$table .= '<th>' . rex_escape($addon->i18n('statistics_google_campaigns_click_ids')) . '</th>';
$table .= '<th>' . rex_escape($addon->i18n('statistics_google_campaigns_params')) . '</th>';
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
    $table .= '<td><a href="' . rex_escape($campaignDetailUrl) . '">' . rex_escape((string) $group['campaign_label']) . '</a></td>';
    $table .= '<td>' . rex_escape((string) $group['landing_path']) . '</td>';
    $table .= '<td data-sort="' . rex_escape((string) $group['count']) . '">' . rex_escape((string) $group['count']) . '</td>';
    $table .= '<td data-sort="' . rex_escape((string) count($group['click_ids'])) . '">' . rex_escape((string) count($group['click_ids'])) . '</td>';
    $table .= '<td title="' . rex_escape($paramsText) . '">' . rex_escape($paramsText) . '</td>';
    $table .= '</tr>';
}

$table .= '</tbody></table>';

echo StatsSubpageRenderer::renderSection($addon->i18n('statistics_google_campaigns_groups'), $intro . $kpi . $buttons . $table);

if ('' !== $requestCampaignKey) {
    $decodedSignature = base64_decode($requestCampaignKey, true);
    if (is_string($decodedSignature) && isset($groups[$decodedSignature])) {
        $selected = $groups[$decodedSignature];
        usort($selected['urls'], static fn (array $a, array $b): int => ($b['count'] <=> $a['count']));

        $detailBody = '<div style="margin-bottom:10px;"><strong>' . rex_escape($selected['campaign_label']) . '</strong> - ' . rex_escape($selected['landing_path']) . '</div>';
        $detailBody .= '<p><a class="btn btn-default" href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer">' . rex_escape($addon->i18n('statistics_google_campaigns_open_ads')) . '</a></p>';
        $detailBody .= '<table class="table table-striped table-hover table-bordered"><thead><tr>';
        $detailBody .= '<th>' . rex_escape($addon->i18n('statistics_url')) . '</th>';
        $detailBody .= '<th>' . rex_escape($addon->i18n('statistics_count')) . '</th>';
        $detailBody .= '</tr></thead><tbody>';

        foreach ($selected['urls'] as $urlEntry) {
            $detailBody .= '<tr>';
            $detailBody .= '<td>' . rex_escape((string) $urlEntry['url']) . '</td>';
            $detailBody .= '<td data-sort="' . rex_escape((string) $urlEntry['count']) . '">' . rex_escape((string) $urlEntry['count']) . '</td>';
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
