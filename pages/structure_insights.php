<?php

$addon = rex_addon::get('statistics');

$clangId = rex_request('clang', 'int', rex_clang::getCurrentId());
if (!rex_clang::exists($clangId)) {
    $clangId = rex_clang::getStartId();
}

$metaFromRequest = rex_request('meta', 'array', []);
$selectedMetaFields = [];
foreach ($metaFromRequest as $field) {
    $field = trim((string) $field);
    if ('' !== $field) {
        $selectedMetaFields[] = $field;
    }
}
$selectedMetaFields = array_values(array_unique($selectedMetaFields));

$availableMetaFields = [];
foreach (rex_sql::showColumns(rex::getTable('article')) as $column) {
    $name = (string) $column['name'];
    if ('' === $name) {
        continue;
    }

    if (str_starts_with($name, 'art_') || str_starts_with($name, 'cat_')) {
        $availableMetaFields[] = $name;
    }
}
sort($availableMetaFields);

if ([] === $selectedMetaFields) {
    foreach (['art_teaser', 'art_keywords', 'cat_description', 'cat_keywords'] as $defaultField) {
        if (in_array($defaultField, $availableMetaFields, true)) {
            $selectedMetaFields[] = $defaultField;
        }
    }
}

$selectedMetaFields = array_values(array_filter(
    $selectedMetaFields,
    static fn(string $field): bool => in_array($field, $availableMetaFields, true),
));

$metaTitles = [];
if ([] !== $selectedMetaFields) {
    $sql = rex_sql::factory();
    $params = [];
    $placeholders = [];

    foreach ($selectedMetaFields as $index => $fieldName) {
        $paramKey = 'meta_' . $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = $fieldName;
    }

    $rows = $sql->getArray(
        'SELECT name, title FROM ' . rex::getTable('metainfo_field') . ' WHERE name IN (' . implode(', ', $placeholders) . ')',
        $params,
    );
    foreach ($rows as $row) {
        $metaTitles[(string) $row['name']] = (string) $row['title'];
    }
}

$resolveEditorLabel = static function (string $editorRaw): string {
    static $cache = [];

    $editorRaw = trim($editorRaw);
    if ('' === $editorRaw) {
        return '-';
    }

    if (isset($cache[$editorRaw])) {
        return $cache[$editorRaw];
    }

    $user = null;
    if (ctype_digit($editorRaw)) {
        $user = rex_user::get((int) $editorRaw);
    }
    if (!$user) {
        $user = rex_user::forLogin($editorRaw);
    }

    if ($user) {
        $login = (string) $user->getLogin();
        $name = trim((string) $user->getName());
        if ('' !== $name && $name !== $login) {
            return $cache[$editorRaw] = $name . ' (' . $login . ')';
        }

        return $cache[$editorRaw] = $login;
    }

    return $cache[$editorRaw] = $editorRaw;
};

$statusLabel = static function (int $status) use ($addon): string {
    return $status > 0 ? $addon->i18n('statistics_status_online') : $addon->i18n('statistics_status_offline');
};

$resolveDate = static function (string $updatedate, string $createdate): ?DateTimeImmutable {
    $candidate = trim('' !== trim($updatedate) ? $updatedate : $createdate);
    if ('' === $candidate || '0000-00-00 00:00:00' === $candidate || '0000-00-00' === $candidate) {
        return null;
    }

    // Some installations store UNIX timestamps instead of SQL datetime strings.
    if (ctype_digit($candidate)) {
        $timestamp = (int) $candidate;

        // Milliseconds to seconds.
        if (strlen($candidate) >= 13) {
            $timestamp = (int) floor($timestamp / 1000);
        }

        try {
            return (new DateTimeImmutable('@' . $timestamp))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } catch (Throwable) {
            return null;
        }
    }

    try {
        return new DateTimeImmutable($candidate);
    } catch (Throwable) {
        return null;
    }
};

$normalizeDate = static function (string $updatedate, string $createdate) use ($resolveDate): string {
    $date = $resolveDate($updatedate, $createdate);
    if (null === $date) {
        return '-';
    }

    return $date->format('Y-m-d H:i:s');
};

$treeRows = [];

$addArticleRows = static function (array &$rows, array $articles, int $depth, string $path, int $clangId, array $metaFields, callable $statusLabelCb, callable $dateCb, callable $editorCb): void {
    foreach ($articles as $article) {
        if (!$article instanceof rex_article) {
            continue;
        }

        if ($article->isStartArticle()) {
            continue;
        }

        if (rex_category::get($article->getId(), $clangId)) {
            continue;
        }

        $metaValues = [];
        foreach ($metaFields as $metaField) {
            $metaValues[$metaField] = (string) $article->getValue($metaField);
        }

        $rows[] = [
            'depth' => $depth,
            'type' => 'article',
            'id' => $article->getId(),
            'title' => (string) $article->getName(),
            'status' => (int) $article->getValue('status'),
            'status_label' => $statusLabelCb((int) $article->getValue('status')),
            'last_edit' => $dateCb((string) $article->getValue('updatedate'), (string) $article->getValue('createdate')),
            'editor' => $editorCb((string) $article->getValue('updateuser')),
            'path' => $path,
            'meta' => $metaValues,
        ];
    }
};

$walkCategory = static function (
    rex_category $category,
    int $depth,
    int $clangId,
    string $path,
    array $metaFields,
    callable $statusLabelCb,
    callable $dateCb,
    callable $editorCb,
    callable $addArticleRowsCb,
    callable &$walker,
    array &$rows
): void {
    $currentPath = '' === $path ? (string) $category->getName() : $path . ' / ' . (string) $category->getName();

    $metaValues = [];
    foreach ($metaFields as $metaField) {
        $metaValues[$metaField] = (string) $category->getValue($metaField);
    }

    $rows[] = [
        'depth' => $depth,
        'type' => 'category',
        'id' => $category->getId(),
        'title' => (string) $category->getName(),
        'status' => (int) $category->getValue('status'),
        'status_label' => $statusLabelCb((int) $category->getValue('status')),
        'last_edit' => $dateCb((string) $category->getValue('updatedate'), (string) $category->getValue('createdate')),
        'editor' => $editorCb((string) $category->getValue('updateuser')),
        'path' => $currentPath,
        'meta' => $metaValues,
    ];

    $addArticleRowsCb(
        $rows,
        $category->getArticles(false),
        $depth + 1,
        $currentPath,
        $clangId,
        $metaFields,
        $statusLabelCb,
        $dateCb,
        $editorCb,
    );

    foreach ($category->getChildren(false) as $child) {
        $walker(
            $child,
            $depth + 1,
            $clangId,
            $currentPath,
            $metaFields,
            $statusLabelCb,
            $dateCb,
            $editorCb,
            $addArticleRowsCb,
            $walker,
            $rows,
        );
    }
};

$rootCategories = rex_category::getRootCategories(false, $clangId);
foreach ($rootCategories as $rootCategory) {
    $walkCategory(
        $rootCategory,
        0,
        $clangId,
        '',
        $selectedMetaFields,
        $statusLabel,
        $normalizeDate,
        $resolveEditorLabel,
        $addArticleRows,
        $walkCategory,
        $treeRows,
    );
}

$addArticleRows(
    $treeRows,
    rex_article::getRootArticles(false, $clangId),
    0,
    rex_i18n::msg('root_level'),
    $clangId,
    $selectedMetaFields,
    $statusLabel,
    $normalizeDate,
    $resolveEditorLabel,
);

$cutoffTs = (new DateTimeImmutable('-2 years'))->getTimestamp();
$sql = rex_sql::factory();
$updatedTsExpr = "CASE\n"
    . "    WHEN updatedate REGEXP '^[0-9]{10,13}$' THEN IF(CHAR_LENGTH(updatedate) >= 13, FLOOR(CAST(updatedate AS UNSIGNED) / 1000), CAST(updatedate AS UNSIGNED))\n"
    . "    WHEN updatedate IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL\n"
    . "    ELSE UNIX_TIMESTAMP(updatedate)\n"
    . "END";
$createdTsExpr = "CASE\n"
    . "    WHEN createdate REGEXP '^[0-9]{10,13}$' THEN IF(CHAR_LENGTH(createdate) >= 13, FLOOR(CAST(createdate AS UNSIGNED) / 1000), CAST(createdate AS UNSIGNED))\n"
    . "    WHEN createdate IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL\n"
    . "    ELSE UNIX_TIMESTAMP(createdate)\n"
    . "END";
$effectiveTsExpr = 'COALESCE((' . $updatedTsExpr . '), (' . $createdTsExpr . '))';

$statsRows = $sql->getArray(
    'SELECT '
    . 'SUM(CASE WHEN startarticle = 1 AND ' . $effectiveTsExpr . ' IS NOT NULL AND ' . $effectiveTsExpr . ' < :cutoff THEN 1 ELSE 0 END) AS old_categories, '
    . 'SUM(CASE WHEN startarticle = 0 AND ' . $effectiveTsExpr . ' IS NOT NULL AND ' . $effectiveTsExpr . ' < :cutoff THEN 1 ELSE 0 END) AS old_articles, '
    . 'MAX(' . $effectiveTsExpr . ') AS latest_edit_ts '
    . 'FROM ' . rex::getTable('article') . ' WHERE clang_id = :clang',
    ['clang' => $clangId, 'cutoff' => $cutoffTs],
);

$statsRow = $statsRows[0] ?? [];
$oldCategoryCount = (int) ($statsRow['old_categories'] ?? 0);
$oldArticleCount = (int) ($statsRow['old_articles'] ?? 0);

$latestEditTs = (int) ($statsRow['latest_edit_ts'] ?? 0);
if ($latestEditTs > 0) {
    $latestEditFormatted = (new DateTimeImmutable('@' . $latestEditTs))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d H:i:s');
} else {
    $latestEditFormatted = '-';
}
$nowFormatted = (new DateTimeImmutable())->format('d.m.Y H:i');

$activeEditorsRaw = $sql->getArray(
    'SELECT updateuser, COUNT(*) AS edits'
    . ' FROM ' . rex::getTable('article')
    . ' WHERE clang_id = :clang AND updateuser <> ""'
    . ' GROUP BY updateuser'
    . ' ORDER BY edits DESC, updateuser ASC'
    . ' LIMIT 10',
    ['clang' => $clangId],
);

$activeEditors = [];
foreach ($activeEditorsRaw as $editorRow) {
    $activeEditors[] = [
        'label' => $resolveEditorLabel((string) $editorRow['updateuser']),
        'edits' => (int) $editorRow['edits'],
    ];
}

$exportType = rex_request('export', 'string', '');
if ('csv' === $exportType || 'xls' === $exportType) {
    $headers = [
        'Ebene',
        'Baum',
        'Typ',
        'ID',
        'Titel',
        'Status',
        'Zuletzt bearbeitet',
        'Letzter Bearbeiter',
        'Pfad',
    ];

    foreach ($selectedMetaFields as $metaField) {
        $headers[] = $metaTitles[$metaField] ?? $metaField;
    }

    $matrix = [];
    $matrix[] = $headers;

    foreach ($treeRows as $row) {
        $isCategory = 'category' === $row['type'];
        $treePrefix = str_repeat('   ', (int) $row['depth']);
        if ((int) $row['depth'] > 0) {
            $treePrefix .= '|- ';
        }

        $hierarchy = $treePrefix . ($isCategory ? '[K]' : '[A]');
        $treeTitle = $treePrefix . (string) $row['title'];

        $line = [
            (string) $row['depth'],
            $hierarchy,
            $isCategory ? 'Kategorie' : 'Artikel',
            (string) $row['id'],
            $treeTitle,
            (string) $row['status_label'],
            (string) $row['last_edit'],
            (string) $row['editor'],
            (string) $row['path'],
        ];

        foreach ($selectedMetaFields as $metaField) {
            $line[] = (string) ($row['meta'][$metaField] ?? '');
        }

        $matrix[] = $line;
    }

    rex_response::cleanOutputBuffers();

    $filename = 'statistics-structure-insights-' . date('Ymd-His');

    if ('csv' === $exportType) {
        rex_response::setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

        $content = "\xEF\xBB\xBF";
        $content .= "sep=;\r\n";
        foreach ($matrix as $line) {
            $encoded = array_map(static function (string $value): string {
                $value = str_replace(["\r", "\n"], [' ', ' '], $value);
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            }, $line);
            $content .= implode(';', $encoded) . "\r\n";
        }

        rex_response::sendContent($content, 'text/csv; charset=utf-8');
    } else {
        rex_response::setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xls"');

        $content = '';
        foreach ($matrix as $line) {
            $encoded = array_map(static function (string $value): string {
                $value = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $value);
                return $value;
            }, $line);
            $content .= implode("\t", $encoded) . "\r\n";
        }

        // Excel interpretiert UTF-16LE + BOM bei tab-separierten .xls-Texten zuverlässig mit Umlauten.
        if (function_exists('mb_convert_encoding')) {
            $content = "\xFF\xFE" . mb_convert_encoding($content, 'UTF-16LE', 'UTF-8');
            rex_response::sendContent($content, 'application/vnd.ms-excel; charset=UTF-16LE');
        } else {
            rex_response::sendContent($content, 'application/vnd.ms-excel; charset=utf-8');
        }
    }

    exit;
}

echo rex_view::clangSwitchAsButtons(new rex_context([
    'page' => rex_be_controller::getCurrentPage(),
    'clang' => $clangId,
    'meta' => $selectedMetaFields,
]));

$formUrl = rex_url::backendPage(rex_be_controller::getCurrentPage());

echo '<div class="panel panel-default"><div class="panel-body">';
echo '<form method="get" action="' . $formUrl . '" class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">';
echo '<input type="hidden" name="page" value="' . rex_escape(rex_be_controller::getCurrentPage()) . '">';
echo '<input type="hidden" name="clang" value="' . (int) $clangId . '">';
echo '<div style="min-width:360px;flex:1 1 420px;">';
echo '<label for="statistics-structure-meta" style="display:block;margin-bottom:4px;">' . rex_escape($addon->i18n('statistics_structure_metafields')) . '</label>';
echo '<select id="statistics-structure-meta" class="form-control" name="meta[]" multiple size="8" style="width:100%;">';
foreach ($availableMetaFields as $metaField) {
    $selected = in_array($metaField, $selectedMetaFields, true) ? ' selected' : '';
    $title = $metaTitles[$metaField] ?? $metaField;
    echo '<option value="' . rex_escape($metaField) . '"' . $selected . '>' . rex_escape($title) . ' [' . rex_escape($metaField) . ']</option>';
}
echo '</select>';
echo '<small style="display:block;margin-top:4px;color:#6b7c93;">' . rex_escape($addon->i18n('statistics_structure_metafields_note')) . '</small>';
echo '</div>';
echo '<div style="display:flex;gap:8px;align-items:flex-end;">';
echo '<button class="btn btn-primary" type="submit">' . rex_escape($addon->i18n('statistics_filter')) . '</button>';
echo '</div>';
echo '</form>';
echo '</div></div>';

echo '<div class="row" style="margin-bottom:10px;">';
echo '<div class="col-sm-4"><div class="alert alert-warning"><strong>' . rex_escape($addon->i18n('statistics_structure_old_articles')) . ':</strong> ' . $oldArticleCount . '</div></div>';
echo '<div class="col-sm-4"><div class="alert alert-warning"><strong>' . rex_escape($addon->i18n('statistics_structure_old_categories')) . ':</strong> ' . $oldCategoryCount . '</div></div>';
echo '<div class="col-sm-4"><div class="alert alert-info"><strong>' . rex_escape($addon->i18n('statistics_structure_current_state')) . ':</strong> ' . rex_escape($nowFormatted) . '<br><strong>' . rex_escape($addon->i18n('statistics_structure_latest_edit')) . ':</strong> ' . rex_escape($latestEditFormatted) . '</div></div>';
echo '</div>';

if ([] !== $activeEditors) {
    echo '<div class="panel panel-default"><div class="panel-heading"><strong>' . rex_escape($addon->i18n('statistics_structure_most_active_editors')) . '</strong></div><div class="panel-body">';
    echo '<ol style="margin-bottom:0;">';
    foreach ($activeEditors as $editor) {
        echo '<li>' . rex_escape($editor['label']) . ' <span class="text-muted">(' . (int) $editor['edits'] . ')</span></li>';
    }
    echo '</ol>';
    echo '</div></div>';
}

$baseParams = [
    'page' => rex_be_controller::getCurrentPage(),
    'clang' => $clangId,
];
foreach ($selectedMetaFields as $metaField) {
    $baseParams['meta'][] = $metaField;
}

$csvUrl = rex_url::backendPage(rex_be_controller::getCurrentPage(), array_merge($baseParams, ['export' => 'csv']));
$xlsUrl = rex_url::backendPage(rex_be_controller::getCurrentPage(), array_merge($baseParams, ['export' => 'xls']));
$xlsxUrl = '#';

echo '<div style="margin-bottom:10px;display:flex;gap:8px;">';
echo '<a class="btn btn-default js-statistics-structure-export" data-pjax="false" href="' . $csvUrl . '">' . rex_escape($addon->i18n('statistics_structure_export_csv')) . '</a>';
echo '<a class="btn btn-default js-statistics-structure-export" data-export-format="xlsx" data-export-fallback-url="' . $xlsUrl . '" data-pjax="false" href="' . $xlsxUrl . '">' . rex_escape($addon->i18n('statistics_structure_export_xlsx')) . '</a>';
echo '</div>';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading">';
echo '<div class="statistics-graph-heading">';
echo '<strong>' . rex_escape($addon->i18n('statistics_structure_graph_title')) . '</strong>';
echo '<div class="btn-group btn-group-xs" role="group" aria-label="Graph actions">';
echo '<button type="button" class="btn btn-default" id="statistics-graph-open-modal">' . rex_escape($addon->i18n('statistics_structure_graph_open_modal')) . '</button>';
echo '<button type="button" class="btn btn-default" id="statistics-graph-export-svg">' . rex_escape($addon->i18n('statistics_structure_graph_export_svg')) . '</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="panel-body">';
echo '<div id="statistics-structure-graph" data-empty-text="' . rex_escape($addon->i18n('statistics_structure_graph_empty')) . '" data-root-label="' . rex_escape($addon->i18n('statistics_structure_graph_root')) . '"></div>';
echo '</div>';
echo '</div>';

echo '<div class="modal fade" id="statistics-graph-modal" tabindex="-1" role="dialog" aria-labelledby="statistics-graph-modal-title">';
echo '  <div class="modal-dialog modal-lg" role="document">';
echo '    <div class="modal-content">';
echo '      <div class="modal-header">';
echo '        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
echo '        <h4 class="modal-title" id="statistics-graph-modal-title">' . rex_escape($addon->i18n('statistics_structure_graph_title')) . '</h4>';
echo '      </div>';
echo '      <div class="modal-body">';
echo '        <div class="statistics-graph-modal-toolbar">';
echo '          <div class="btn-group btn-group-sm" role="group" aria-label="Zoom">';
echo '            <button type="button" class="btn btn-default" data-graph-zoom="out">' . rex_escape($addon->i18n('statistics_structure_graph_zoom_out')) . '</button>';
echo '            <button type="button" class="btn btn-default" data-graph-zoom="in">' . rex_escape($addon->i18n('statistics_structure_graph_zoom_in')) . '</button>';
echo '            <button type="button" class="btn btn-default" data-graph-zoom="reset">' . rex_escape($addon->i18n('statistics_structure_graph_zoom_reset')) . '</button>';
echo '          </div>';
echo '          <div class="btn-group btn-group-sm" role="group" aria-label="Export">';
echo '            <button type="button" class="btn btn-default" data-graph-export="svg">' . rex_escape($addon->i18n('statistics_structure_graph_export_svg')) . '</button>';
echo '          </div>';
echo '        </div>';
echo '        <div id="statistics-graph-modal-canvas"></div>';
echo '      </div>';
echo '    </div>';
echo '  </div>';
echo '</div>';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><strong>' . rex_escape($addon->i18n('statistics_structure_tree_title')) . '</strong></div>';
echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover">';
echo '<thead><tr>';
echo '<th>' . rex_escape($addon->i18n('statistics_structure_type')) . '</th>';
echo '<th>' . rex_escape(rex_i18n::msg('header_id')) . '</th>';
echo '<th>' . rex_escape($addon->i18n('statistics_structure_title')) . '</th>';
echo '<th>' . rex_escape(rex_i18n::msg('status')) . '</th>';
echo '<th>' . rex_escape($addon->i18n('statistics_structure_last_edit')) . '</th>';
echo '<th>' . rex_escape($addon->i18n('statistics_structure_last_editor')) . '</th>';
echo '<th>' . rex_escape($addon->i18n('statistics_structure_path')) . '</th>';
foreach ($selectedMetaFields as $metaField) {
    echo '<th>' . rex_escape($metaTitles[$metaField] ?? $metaField) . '</th>';
}
echo '</tr></thead><tbody>';

foreach ($treeRows as $row) {
    $isCategory = 'category' === $row['type'];
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int) $row['depth']);
    $marker = $isCategory ? '[K] ' : '[A] ';

    echo '<tr data-depth="' . (int) $row['depth'] . '">';
    echo '<td>' . ($isCategory ? rex_escape($addon->i18n('statistics_structure_category')) : rex_escape($addon->i18n('statistics_structure_article'))) . '</td>';
    echo '<td>' . (int) $row['id'] . '</td>';
    echo '<td>' . $indent . $marker . rex_escape((string) $row['title']) . '</td>';
    echo '<td>' . rex_escape((string) $row['status_label']) . '</td>';
    echo '<td>' . rex_escape((string) $row['last_edit']) . '</td>';
    echo '<td>' . rex_escape((string) $row['editor']) . '</td>';
    echo '<td>' . rex_escape((string) $row['path']) . '</td>';

    foreach ($selectedMetaFields as $metaField) {
        echo '<td>' . rex_escape((string) ($row['meta'][$metaField] ?? '')) . '</td>';
    }

    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
echo '</div>';
