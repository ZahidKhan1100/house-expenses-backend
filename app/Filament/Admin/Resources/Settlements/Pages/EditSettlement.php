<?php

namespace App\Filament\Admin\Resources\Settlements\Pages;

use App\Filament\Admin\Resources\Settlements\SettlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSettlement extends EditRecord
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
