<?php

namespace App\Filament\Resources\G003M004BuildingResource\Pages;

use App\Filament\Resources\G003M004BuildingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG003M004Buildings extends ListRecords
{
    protected static string $resource = G003M004BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
