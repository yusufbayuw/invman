<?php

namespace App\Filament\Resources\G003M004BuildingResource\Pages;

use App\Filament\Resources\G003M004BuildingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG003M004Building extends ViewRecord
{
    protected static string $resource = G003M004BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
