<?php

namespace App\Filament\Resources\G005M010RoomReservationResource\Pages;

use App\Filament\Resources\G005M010RoomReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG005M010RoomReservation extends EditRecord
{
    protected static string $resource = G005M010RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
