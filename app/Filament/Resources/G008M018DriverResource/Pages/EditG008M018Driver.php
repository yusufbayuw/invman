<?php

namespace App\Filament\Resources\G008M018DriverResource\Pages;

use App\Filament\Resources\G008M018DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG008M018Driver extends EditRecord
{
    protected static string $resource = G008M018DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
