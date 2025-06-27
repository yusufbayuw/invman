<?php

namespace App\Filament\Resources\G005M010RoomReservationResource\Pages;

use App\Filament\Resources\G005M010RoomReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG005M010RoomReservations extends ListRecords
{
    protected static string $resource = G005M010RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
