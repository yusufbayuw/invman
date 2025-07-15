<?php

namespace App\Filament\Resources\G005M019VehicleReservationResource\Pages;

use App\Filament\Resources\G005M019VehicleReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG005M019VehicleReservations extends ListRecords
{
    protected static string $resource = G005M019VehicleReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
