<?php

namespace App\Filament\Admin\Widgets;

use App\Models\House;
use App\Models\Record;
use App\Models\Settlement;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class DashboardKpisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyExpenseTotal = (float) Record::query()
            ->whereBetween('timestamp', [$monthStart, $monthEnd])
            ->sum('amount');

        $pendingSettlements = Settlement::query()
            ->where('status', 'pending')
            ->count();

        return [
            Stat::make('Houses', Number::format(House::query()->count()))
                ->description('Total houses')
                ->icon(Heroicon::OutlinedHome),
            Stat::make('Users', Number::format(User::query()->count()))
                ->description('App users')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('Expenses (this month)', Number::currency($monthlyExpenseTotal, 'USD'))
                ->description('Sum of record amounts')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Outstanding settlements', Number::format($pendingSettlements))
                ->description('Pending rows')
                ->icon(Heroicon::OutlinedArrowsRightLeft),
        ];
    }
}
