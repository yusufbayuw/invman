<?php

namespace App\Filament\Resources\G005M019VehicleReservationResource\Pages;

use App\Filament\Resources\G005M019VehicleReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG005M019VehicleReservation extends EditRecord
{
    protected static string $resource = G005M019VehicleReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
