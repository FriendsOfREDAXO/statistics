<?php

namespace AndiLeni\Statistics;

use rex_config;

class StatsChartConfig
{
    public static function isToolboxEnabled(): bool
    {
        return (bool) rex_config::get('statistics', 'statistics_show_chart_toolbox');
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, string|int|float> $values
     * @return array<string, mixed>
     */
    public static function buildTimelineOption(array $labels, array $values): array
    {
        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
            ],
            'dataZoom' => [[
                'id' => 'dataZoomX',
                'type' => 'slider',
                'xAxisIndex' => [0],
                'filterMode' => 'filter',
            ]],
            'grid' => [
                'left' => '5%',
                'right' => '5%',
            ],
            'toolbox' => [
                'show' => self::isToolboxEnabled(),
                'feature' => [
                    'dataZoom' => [
                        'yAxisIndex' => 'none',
                    ],
                    'dataView' => [
                        'readOnly' => false,
                    ],
                    'magicType' => [
                        'type' => ['line', 'bar', 'stack'],
                    ],
                    'restore' => (object) [],
                    'saveAsImage' => (object) [],
                ],
            ],
            'legend' => (object) [],
            'xAxis' => [
                'data' => $labels,
                'type' => 'category',
            ],
            'yAxis' => (object) [],
            'series' => [[
                'data' => $values,
                'type' => 'line',
            ]],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $source
     * @return array<string, mixed>
     */
    public static function buildPageOverviewOption(array $source): array
    {
        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => '{@url}<br />Status: <b>{@status}</b><br />Anzahl: <b>{@count}</b>',
            ],
            'dataZoom' => [[
                'id' => 'dataZoomX',
                'type' => 'slider',
                'xAxisIndex' => [0],
                'filterMode' => 'filter',
            ]],
            'grid' => [
                'left' => '5%',
                'right' => '5%',
            ],
            'toolbox' => [
                'show' => self::isToolboxEnabled(),
                'feature' => [
                    'dataZoom' => [
                        'yAxisIndex' => 'none',
                    ],
                    'dataView' => [
                        'readOnly' => false,
                    ],
                    'magicType' => [
                        'type' => ['line', 'bar', 'stack'],
                    ],
                    'restore' => (object) [],
                    'saveAsImage' => (object) [],
                ],
            ],
            'legend' => [
                'show' => true,
            ],
            'xAxis' => [[
                'type' => 'category',
            ]],
            'yAxis' => [
                'type' => 'value',
            ],
            'series' => [
                [
                    'datasetId' => 'ds0',
                    'stack' => 'stack1',
                    'type' => 'bar',
                    'encode' => [
                        'x' => 'url',
                        'y' => 'zero',
                    ],
                ],
                [
                    'name' => '200',
                    'datasetId' => 'ds1',
                    'type' => 'bar',
                    'encode' => [
                        'x' => 'url',
                        'y' => 'count',
                    ],
                    'stack' => 'stack1',
                    'color' => '#198754',
                ],
                [
                    'name' => 'nicht-200',
                    'datasetId' => 'ds2',
                    'type' => 'bar',
                    'encode' => [
                        'x' => 'url',
                        'y' => 'count',
                    ],
                    'stack' => 'stack1',
                    'color' => '#c12e34',
                ],
            ],
            'dataset' => [
                [
                    'id' => 'dataset_raw',
                    'dimensions' => ['url', 'count', 'status', 'zero'],
                    'source' => $source,
                ],
                [
                    'id' => 'ds0',
                    'fromDatasetId' => 'dataset_raw',
                    'transform' => [[
                        'type' => 'sort',
                        'config' => [
                            'dimension' => 'count',
                            'order' => 'desc',
                        ],
                    ]],
                ],
                [
                    'id' => 'ds1',
                    'fromDatasetId' => 'ds0',
                    'transform' => [[
                        'type' => 'filter',
                        'config' => [
                            'dimension' => 'status',
                            '=' => '200 OK',
                        ],
                    ]],
                ],
                [
                    'id' => 'ds2',
                    'fromDatasetId' => 'ds0',
                    'transform' => [[
                        'type' => 'filter',
                        'config' => [
                            'dimension' => 'status',
                            '!=' => '200 OK',
                        ],
                    ]],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function buildPagesStackedBarOption(array $rows, int $limit = 30): array
    {
        $rows = array_slice($rows, 0, max(1, $limit));

        $labels = [];
        $okValues = [];
        $notOkValues = [];

        foreach ($rows as $row) {
            $url = (string) ($row['url'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            $status = (string) ($row['status'] ?? '-');

            $label = $url;
            if (strlen($label) > 90) {
                $label = substr($label, 0, 87) . '...';
            }

            $labels[] = $label;

            if ('200 OK' === $status) {
                $okValues[] = $count;
                $notOkValues[] = 0;
            } else {
                $okValues[] = 0;
                $notOkValues[] = $count;
            }
        }

        return [
            'title' => (object) [],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'show' => true,
                'bottom' => 0,
            ],
            'grid' => [
                'left' => '30%',
                'right' => '4%',
                'top' => '3%',
                'bottom' => '13%',
                'containLabel' => true,
            ],
            'toolbox' => [
                'show' => self::isToolboxEnabled(),
                'feature' => [
                    'dataZoom' => [
                        'yAxisIndex' => 'none',
                    ],
                    'dataView' => [
                        'readOnly' => false,
                    ],
                    'magicType' => [
                        'type' => ['bar', 'stack'],
                    ],
                    'restore' => (object) [],
                    'saveAsImage' => (object) [],
                ],
            ],
            'xAxis' => [[
                'type' => 'value',
                'minInterval' => 1,
            ]],
            'yAxis' => [[
                'type' => 'category',
                'data' => $labels,
                'inverse' => true,
                'axisTick' => ['show' => false],
                'axisLabel' => [
                    'fontSize' => 11,
                ],
            ]],
            'dataZoom' => [[
                'type' => 'inside',
                'yAxisIndex' => [0],
                'filterMode' => 'none',
            ]],
            'series' => [
                [
                    'name' => '200',
                    'type' => 'bar',
                    'stack' => 'status',
                    'data' => $okValues,
                    'itemStyle' => [
                        'color' => '#198754',
                        'borderRadius' => [0, 3, 3, 0],
                    ],
                ],
                [
                    'name' => 'nicht-200',
                    'type' => 'bar',
                    'stack' => 'status',
                    'data' => $notOkValues,
                    'itemStyle' => [
                        'color' => '#c12e34',
                        'borderRadius' => [0, 3, 3, 0],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $option
     */
    public static function renderScript(string $targetId, array $option): string
    {
        return '<script type="application/json" data-statistics-chart-config data-target-id="' . htmlspecialchars($targetId, ENT_QUOTES) . '">' . json_encode($option, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}