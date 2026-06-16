<?php

return [
    'collector_token' => env('SPEEDWAY_COLLECTOR_TOKEN'),
    'collector_status_path' => env(
        'COLLECTOR_STATUS_PATH',
        base_path('collector/storage/collector-status.json'),
    ),
];
