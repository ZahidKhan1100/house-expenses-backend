<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return "houseapp://reset-password?token={$token}&email={$user->email}";
        });

        RateLimiter::for('receipt-scan', function (Request $request) {
            $per = (int) config('houseexpenses.receipt_scan.per_minute', 10);
            $per = max(1, $per);

            return Limit::perMinute($per)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        Gate::define('viewPulse', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }
            $allowed = array_filter(array_map('trim', explode(',', (string) env('PULSE_ALLOWED_EMAILS', ''))));
            if ($user === null || empty($allowed)) {
                return false;
            }

            return in_array((string) ($user->email ?? ''), $allowed, true);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
