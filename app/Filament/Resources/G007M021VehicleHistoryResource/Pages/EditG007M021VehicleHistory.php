<?php

namespace App\Filament\Resources\G007M021VehicleHistoryResource\Pages;

use App\Filament\Resources\G007M021VehicleHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG007M021VehicleHistory extends EditRecord
{
    protected static string $resource = G007M021VehicleHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
