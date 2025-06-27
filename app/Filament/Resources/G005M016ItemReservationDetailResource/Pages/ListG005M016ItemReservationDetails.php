<?php

namespace App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;

use App\Filament\Resources\G005M016ItemReservationDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG005M016ItemReservationDetails extends ListRecords
{
    protected static string $resource = G005M016ItemReservationDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
