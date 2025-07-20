<?php

namespace App\Filament\Resources\G009M024VehicleChecklistResource\Pages;

use App\Filament\Resources\G009M024VehicleChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG009M024VehicleChecklist extends EditRecord
{
    protected static string $resource = G009M024VehicleChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
