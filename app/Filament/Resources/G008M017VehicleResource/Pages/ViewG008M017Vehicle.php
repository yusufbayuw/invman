<?php

namespace App\Filament\Resources\G008M017VehicleResource\Pages;

use App\Filament\Resources\G008M017VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG008M017Vehicle extends ViewRecord
{
    protected static string $resource = G008M017VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
