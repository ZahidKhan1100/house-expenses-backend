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
        /** @see https://ai.google.dev/gemini-api/docs/models/gemini — 1.5 ids were removed from many keys; prefer 2.5.x */
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile app — password reset deep link (must match Expo `scheme` + Android package)
    |--------------------------------------------------------------------------
    | Email sends users to HTTPS /reset-password; that page opens the app via custom scheme.
    */
    'password_reset' => [
        'mobile_scheme' => env('PASSWORD_RESET_MOBILE_SCHEME', 'com.ihabimate.habimate'),
        'android_package' => env('PASSWORD_RESET_ANDROID_PACKAGE', 'com.ihabimate.habimate'),
        /** Public URL in the reset email (no trailing slash). */
        'web_base' => rtrim(env('PASSWORD_RESET_WEB_BASE', 'https://habimate.com'), '/'),
    ],

];
