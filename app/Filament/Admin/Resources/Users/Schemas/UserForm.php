<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Select::make('role')
                    ->options([
                        'admin' => 'admin',
                        'mate' => 'mate',
                    ])
                    ->required(),
                Select::make('status')
                    ->options([
                        'active' => 'active',
                        'inactive' => 'inactive',
                    ])
                    ->nullable(),
                Select::make('house_id')
                    ->relationship('house', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('karma_balance')
                    ->numeric()
                    ->nullable(),
            ]);
    }
}
