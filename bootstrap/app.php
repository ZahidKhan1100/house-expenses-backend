<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Trust proxy headers when the app sits behind Railway, Cloudflare, ALB, etc.
         * Needed for HTTPS detection, URLs, cookies, and accurate client IPs (`ADMIN_ALLOWED_IPS`).
         *
         * Env:
         *   TRUSTED_PROXIES unset or empty → in production trust *; elsewhere do not trust.
         *   TRUSTED_PROXIES=* → trust all proxies.
         *   TRUSTED_PROXIES=ip1,ip2 → comma-separated proxy identifiers Laravel accepts.
         *   TRUSTED_PROXIES=false → disable (direct socket / local).
         */
        $raw = env('TRUSTED_PROXIES');
        $doTrust = false;
        /** @var array<int, string>|string $at */
        $at = '*';

        $isProduction = (env('APP_ENV') ?? getenv('APP_ENV') ?: 'local') === 'production';

        if ($raw === null || $raw === '') {
            $doTrust = $isProduction;
            $at = '*';
        } else {
            $value = strtolower(trim((string) $raw));
            if (in_array($value, ['false', '0', 'no'], true)) {
                $doTrust = false;
            } elseif ($value === '*' || in_array($value, ['true', '1', 'yes'], true)) {
                $doTrust = true;
                $at = '*';
            } elseif (str_contains((string) $raw, ',')) {
                $doTrust = true;
                $parts = array_values(array_filter(array_map(trim(...), explode(',', (string) $raw))));
                $at = $parts !== [] ? $parts : '*';
            } else {
                $doTrust = true;
                $at = trim((string) $raw);
            }
        }

        if ($doTrust) {
            $middleware->trustProxies(
                at: $at,
                headers: Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_HOST |
                    Request::HEADER_X_FORWARDED_PORT |
                    Request::HEADER_X_FORWARDED_PROTO |
                    Request::HEADER_FORWARDED |
                    Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
