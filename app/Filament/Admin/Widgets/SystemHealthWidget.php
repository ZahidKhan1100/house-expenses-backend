<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            $this->queueStat(),
            $this->diskStat(),
            $this->latencyStat(),
        ];
    }

    protected function queueStat(): Stat
    {
        $driver = (string) config('queue.default');

        if ($driver === 'sync') {
            return Stat::make('Queue', 'sync')
                ->description('Driver is sync — jobs run in-process')
                ->color('gray')
                ->icon(Heroicon::OutlinedQueueList);
        }

        $pending = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : null;
        $failed = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : null;

        if ($pending === null) {
            return Stat::make('Queue', $driver)
                ->description('No jobs table — check your queue backend')
                ->color('warning')
                ->icon(Heroicon::OutlinedQueueList);
        }

        $failedColor = $failed > 0 ? 'danger' : 'success';

        return Stat::make('Queue jobs', number_format($pending))
            ->description($failed !== null ? "Failed: {$failed} · Driver: {$driver}" : "Driver: {$driver}")
            ->color($failedColor)
            ->icon(Heroicon::OutlinedQueueList);
    }

    protected function diskStat(): Stat
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return Stat::make('Storage', 'n/a')
                ->description('Could not read disk space')
                ->color('gray')
                ->icon(Heroicon::OutlinedServer);
        }

        $usedPct = round(100 * (1 - ($free / $total)), 1);
        $freeGb = round($free / 1_073_741_824, 1);

        $color = match (true) {
            $usedPct >= 95 => 'danger',
            $usedPct >= 85 => 'warning',
            default => 'success',
        };

        return Stat::make('Storage (app)', "{$usedPct}% used")
            ->description("≈ {$freeGb} GiB free under storage/")
            ->color($color)
            ->icon(Heroicon::OutlinedServer);
    }

    protected function latencyStat(): Stat
    {
        $url = url('/up');
        $start = microtime(true);

        try {
            $response = Http::timeout(5)->get($url);
            $ms = (int) round((microtime(true) - $start) * 1000);
            $ok = $response->successful();
        } catch (\Throwable) {
            $ms = (int) round((microtime(true) - $start) * 1000);
            $ok = false;
        }

        $color = $ok ? ($ms > 2000 ? 'warning' : 'success') : 'danger';

        return Stat::make('Health endpoint', $ok ? "{$ms} ms" : 'unreachable')
            ->description($ok ? "GET {$url}" : "Failed: {$url}")
            ->color($color)
            ->icon(Heroicon::OutlinedSignal);
    }
}
