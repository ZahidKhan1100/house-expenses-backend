<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\WallSnippetExpiryService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    try {
        app(WallSnippetExpiryService::class)->cleanupExpiredSnippets();
    } catch (\Throwable $e) {
    }
})->daily();
