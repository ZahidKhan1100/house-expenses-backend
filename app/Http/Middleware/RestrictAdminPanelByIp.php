<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictAdminPanelByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('admin.allowed_ips', []);

        if ($allowed === []) {
            return $next($request);
        }

        $ip = $request->ip();

        if (! in_array($ip, $allowed, true)) {
            abort(403, 'Admin panel access is not allowed from this address.');
        }

        return $next($request);
    }
}
