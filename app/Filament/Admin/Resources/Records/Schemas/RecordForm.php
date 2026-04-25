<?php

namespace App\Filament\Admin\Resources\Records\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expense_id')
                    ->relationship('expense', 'month')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('added_by')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('added_by_name')
                    ->required()
                    ->maxLength(255),
                Select::make('paid_by')
                    ->relationship('payer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('paid_by_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('description')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('split_method')
                    ->maxLength(64)
                    ->default('equal'),
                TextInput::make('bill_period_days')
                    ->numeric()
                    ->nullable(),
                DateTimePicker::make('timestamp')
                    ->required(),
            ]);
    }
}
