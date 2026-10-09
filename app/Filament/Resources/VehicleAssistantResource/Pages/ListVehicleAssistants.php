<?php

namespace App\Filament\Resources\VehicleAssistantResource\Pages;

use App\Filament\Resources\VehicleAssistantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleAssistants extends ListRecords
{
    protected static string $resource = VehicleAssistantResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
