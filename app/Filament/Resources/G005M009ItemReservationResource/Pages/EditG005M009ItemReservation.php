<?php

namespace App\Filament\Resources\G005M009ItemReservationResource\Pages;

use App\Filament\Resources\G005M009ItemReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG005M009ItemReservation extends EditRecord
{
    protected static string $resource = G005M009ItemReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
