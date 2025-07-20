<?php

namespace App\Filament\Resources\G009M024VehicleChecklistResource\Pages;

use App\Filament\Resources\G009M024VehicleChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG009M024VehicleChecklists extends ListRecords
{
    protected static string $resource = G009M024VehicleChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
