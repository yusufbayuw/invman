<?php

namespace App\Filament\Resources\G007M021VehicleHistoryResource\Pages;

use App\Filament\Resources\G007M021VehicleHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG007M021VehicleHistories extends ListRecords
{
    protected static string $resource = G007M021VehicleHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
