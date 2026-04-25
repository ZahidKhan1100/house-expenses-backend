<?php

namespace App\Filament\Admin\Resources\ExpenseAuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->sortable(),
                TextColumn::make('summary')
                    ->limit(40),
                TextColumn::make('house_id')
                    ->label('House')
                    ->sortable(),
                TextColumn::make('expense_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('record_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
