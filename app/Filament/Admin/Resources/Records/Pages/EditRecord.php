<?php

namespace App\Filament\Admin\Resources\Records\Pages;

use App\Filament\Admin\Resources\Records\RecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;

class EditRecord extends BaseEditRecord
{
    protected static string $resource = RecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
