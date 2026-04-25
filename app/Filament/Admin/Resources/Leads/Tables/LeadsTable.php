<?php

namespace App\Filament\Admin\Resources\Leads\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bug_report' => 'Bug report',
                        'feature_idea' => 'Feature idea',
                        'partnership' => 'Partnership',
                        'lead_magnet' => 'Lead magnet',
                        default => $state ?? '—',
                    })
                    ->sortable(),
                TextColumn::make('message')
                    ->limit(60)
                    ->tooltip(fn ($record): ?string => $record->message ?? null),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'bug_report' => 'Bug report',
                        'feature_idea' => 'Feature idea',
                        'partnership' => 'Business partnership',
                        'lead_magnet' => 'Resource download / templates',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
