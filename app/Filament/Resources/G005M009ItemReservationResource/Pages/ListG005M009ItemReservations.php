<?php

namespace App\Filament\Resources\G005M009ItemReservationResource\Pages;

use App\Filament\Resources\G005M009ItemReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG005M009ItemReservations extends ListRecords
{
    protected static string $resource = G005M009ItemReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
