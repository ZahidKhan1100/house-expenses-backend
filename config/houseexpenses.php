<?php

/**
 * Canonical public base URL where this Laravel app responds (HTTPS, no trailing slash).
 * Critical for emailed links when APP_URL differs from production (e.g. Railway internal hostname).
 *
 * Prefer explicit PUBLIC_APP_URL (e.g. https://api.habimate.com) so verify-email URLs never
 * hit the Next.js domain (would 404 on /api/v1/...).
 */
function houseexpenses_public_app_url(): string
{
    $raw = env('PUBLIC_APP_URL', env('APP_URL', 'http://localhost'));

    return rtrim((string) $raw, '/');
}

return [

    'public_app_url' => houseexpenses_public_app_url(),

    /*
    |--------------------------------------------------------------------------
    | Split / balance cache (Payments, Settlements)
    |--------------------------------------------------------------------------
    | Uses Laravel Cache. Set SPLIT_BALANCE_CACHE_STORE=redis in production and
    | ensure CACHE_STORE or this store points at Redis.
    */
    /** Bumped when split math changes; exposed on /payments so clients can verify deploys. */
    'split_algorithm_version' => env('SPLIT_ALGORITHM_VERSION', 'v6-exact-cents'),

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
