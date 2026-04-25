<?php

namespace App\Filament\Admin\Resources\Settlements\Pages;

use App\Filament\Admin\Resources\Settlements\SettlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSettlements extends ListRecords
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
