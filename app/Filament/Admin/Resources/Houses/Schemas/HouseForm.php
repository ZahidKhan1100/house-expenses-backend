<?php

namespace App\Filament\Admin\Resources\Houses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->maxLength(255),
                Select::make('admin_id')
                    ->relationship('admin', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('currency')
                    ->maxLength(8)
                    ->default('USD'),
                TextInput::make('guest_day_weight_percent')
                    ->numeric()
                    ->default(0),
                TextInput::make('leaderboard_top_user_id')
                    ->numeric()
                    ->nullable()
                    ->label('Leaderboard top user ID'),
            ]);
    }
}
