<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ExpenseAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAuditActivityWidget extends TableWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent expense audit activity')
            ->query(
                ExpenseAuditLog::query()->latest('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->sortable(),
                TextColumn::make('summary')
                    ->limit(50)
                    ->tooltip(fn (ExpenseAuditLog $record): ?string => $record->summary),
                TextColumn::make('house_id')
                    ->label('House')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
