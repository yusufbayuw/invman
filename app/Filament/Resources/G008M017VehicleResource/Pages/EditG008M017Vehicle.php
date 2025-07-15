<?php

namespace App\Filament\Resources\G008M017VehicleResource\Pages;

use App\Filament\Resources\G008M017VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG008M017Vehicle extends EditRecord
{
    protected static string $resource = G008M017VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
