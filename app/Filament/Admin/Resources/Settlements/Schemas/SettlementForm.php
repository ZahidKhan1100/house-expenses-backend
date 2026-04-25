<?php

namespace App\Filament\Admin\Resources\Settlements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettlementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('house_id')
                    ->relationship('house', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('month')
                    ->required()
                    ->maxLength(7),
                Select::make('from_user_id')
                    ->relationship('fromUser', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('to_user_id')
                    ->relationship('toUser', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('from_name')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('to_name')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('amount')
                    ->numeric()
                    ->required(),
                TextInput::make('source')
                    ->maxLength(16)
                    ->default('engine'),
                TextInput::make('type')
                    ->maxLength(32)
                    ->default('expense'),
                TextInput::make('title')
                    ->maxLength(255)
                    ->nullable(),
                Textarea::make('note')
                    ->nullable(),
                Select::make('status')
                    ->options([
                        'pending' => 'pending',
                        'paid' => 'paid',
                        'completed' => 'completed',
                    ])
                    ->required(),
                DateTimePicker::make('settled_at')
                    ->nullable(),
            ]);
    }
}
