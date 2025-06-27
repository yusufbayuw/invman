<?php

namespace App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;

use App\Filament\Resources\G005M016ItemReservationDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG005M016ItemReservationDetail extends ViewRecord
{
    protected static string $resource = G005M016ItemReservationDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
