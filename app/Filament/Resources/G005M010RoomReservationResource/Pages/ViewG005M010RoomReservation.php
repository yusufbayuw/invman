<?php

namespace App\Filament\Resources\G005M010RoomReservationResource\Pages;

use App\Filament\Resources\G005M010RoomReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG005M010RoomReservation extends ViewRecord
{
    protected static string $resource = G005M010RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
