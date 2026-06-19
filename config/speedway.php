<?php

return [
    'collector_token' => env('SPEEDWAY_COLLECTOR_TOKEN'),
    'collector_status_path' => env(
        'COLLECTOR_STATUS_PATH',
        base_path('collector/storage/collector-status.json'),
    ),
    'market_odd_estimation' => [
        // Heurísticas provisórias — calibrar com odds reais observadas.
        'forecast_product_multiplier' => 0.65,
        'tricast_product_multiplier' => 0.35,
    ],
];
