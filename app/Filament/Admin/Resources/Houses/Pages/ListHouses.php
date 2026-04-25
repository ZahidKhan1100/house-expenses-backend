<?php

namespace App\Filament\Admin\Resources\Houses\Pages;

use App\Filament\Admin\Resources\Houses\HouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHouses extends ListRecords
{
    protected static string $resource = HouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
