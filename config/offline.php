<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline-first sync configuration
    |--------------------------------------------------------------------------
    */

    'sync_interval_seconds' => (int) env('OFFLINE_SYNC_INTERVAL', 20),

    'health_check_url' => env('OFFLINE_HEALTH_URL', '/operator/offline/health'),

    'lan_base_url' => env('OFFLINE_LAN_BASE_URL', null),

    // Mode options:
    // - cloud_fallback_lan: use cloud first, LAN when cloud unavailable
    // - lan_first: use LAN first when available (recommended for venue hub setup)
    'mode' => env('OFFLINE_MODE', 'cloud_fallback_lan'),
    'prefer_lan' => env('OFFLINE_PREFER_LAN', false),

    'conflict_policy' => env('OFFLINE_CONFLICT_POLICY', 'first_scan_wins'),

    'bootstrap_chunk_size' => (int) env('OFFLINE_BOOTSTRAP_CHUNK', 2000),

    'max_sync_retries' => (int) env('OFFLINE_MAX_SYNC_RETRIES', 8),

    'device_token_header' => 'X-Offline-Device-Id',

    'sync_token' => env('OFFLINE_SYNC_TOKEN', null),

];
