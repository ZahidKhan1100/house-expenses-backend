<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin panel IP allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated client IPs allowed to access /admin. When empty, all
    | IPs are allowed (rely on Nginx/firewall in production). Prefer VPN
    | (e.g. Tailscale) and list those egress IPs or use this middleware
    | together with server-level rules.
    |
    */
    /** Require two-factor (authenticator app) login for /admin staff. */
    'mfa_required' => filter_var(env('FILAMENT_ADMIN_MFA_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
    ))),
];
