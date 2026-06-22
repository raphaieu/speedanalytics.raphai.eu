<?php

return [
    'collector_token' => env('SPEEDWAY_COLLECTOR_TOKEN'),
    'collector_status_path' => env(
        'COLLECTOR_STATUS_PATH',
        base_path('collector/storage/collector-status.json'),
    ),
    'collector_payload_stale_seconds' => (int) env('SPEEDWAY_COLLECTOR_PAYLOAD_STALE_SECONDS', 120),
    'pending_stale_buffer_minutes' => (int) env('SPEEDWAY_PENDING_STALE_BUFFER_MINUTES', 8),
    'pending_external_id_lag' => (int) env('SPEEDWAY_PENDING_EXTERNAL_ID_LAG', 80),
    'race_live_window_minutes' => (int) env('SPEEDWAY_RACE_LIVE_WINDOW_MINUTES', 4),
    'reconciliation_payload_lookback_hours' => (int) env('SPEEDWAY_RECONCILE_LOOKBACK_HOURS', 24),
    'reconciliation_payload_scan_limit' => (int) env('SPEEDWAY_RECONCILE_SCAN_LIMIT', 200),
    'timezone' => env('SPEEDWAY_TIMEZONE', 'America/Sao_Paulo'),
    // Grade virtual BB Tips (Hora/Minutos) = relógio BR + offset. Padrão: BR 20:00 → grade 00:00.
    'race_schedule_offset_hours' => (int) env('SPEEDWAY_RACE_SCHEDULE_OFFSET_HOURS', 4),
    'market_odd_estimation' => [
        // Heurísticas provisórias — calibrar com odds reais observadas.
        'forecast_product_multiplier' => 0.65,
        'tricast_product_multiplier' => 0.35,
    ],
];
