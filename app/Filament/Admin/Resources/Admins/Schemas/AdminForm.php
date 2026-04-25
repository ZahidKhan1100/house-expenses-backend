<?php

namespace App\Filament\Admin\Resources\Admins\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminForm
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
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                CheckboxList::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        fn ($query) => $query->where('guard_name', 'admin'),
                    )
                    ->columns(2)
                    ->searchable(),
            ]);
    }
}
