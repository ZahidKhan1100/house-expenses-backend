<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Split / balance cache (Payments, Settlements)
    |--------------------------------------------------------------------------
    | Uses Laravel Cache. Set SPLIT_BALANCE_CACHE_STORE=redis in production and
    | ensure CACHE_STORE or this store points at Redis.
    */
    'split_balance_cache' => [
        'enabled' => env('SPLIT_BALANCE_CACHE_ENABLED', true),
        'store' => env('SPLIT_BALANCE_CACHE_STORE'),
        'ttl' => (int) env('SPLIT_BALANCE_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt scan (Gemini)
    |--------------------------------------------------------------------------
    */
    'receipt_scan' => [
        'max_width' => (int) env('RECEIPT_IMAGE_MAX_WIDTH', 1000),
        'per_minute' => (int) env('RECEIPT_SCAN_PER_MINUTE', 10),
        /** @see https://ai.google.dev/gemini-api/docs/models/gemini — 1.5-flash works on most AI Studio keys */
        'gemini_model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

];
