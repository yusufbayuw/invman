<?php

namespace App\Filament\Resources\G005M019VehicleReservationResource\Pages;

use App\Filament\Resources\G005M019VehicleReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG005M019VehicleReservation extends ViewRecord
{
    protected static string $resource = G005M019VehicleReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
