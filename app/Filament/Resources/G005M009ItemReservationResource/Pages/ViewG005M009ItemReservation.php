<?php

namespace App\Filament\Resources\G005M009ItemReservationResource\Pages;

use App\Filament\Resources\G005M009ItemReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG005M009ItemReservation extends ViewRecord
{
    protected static string $resource = G005M009ItemReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
