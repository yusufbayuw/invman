<?php

namespace App\Filament\Resources\G008M018DriverResource\Pages;

use App\Filament\Resources\G008M018DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG008M018Driver extends ViewRecord
{
    protected static string $resource = G008M018DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
