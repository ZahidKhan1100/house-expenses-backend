<?php

namespace App\Filament\Admin\Resources\Leads\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('category')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bug_report' => 'Bug report',
                        'feature_idea' => 'Feature idea',
                        'partnership' => 'Business partnership',
                        'lead_magnet' => 'Resource download / templates',
                        default => $state ?? '—',
                    }),
                TextEntry::make('message')
                    ->columnSpanFull(),
                TextEntry::make('ip_address')
                    ->label('IP address'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
