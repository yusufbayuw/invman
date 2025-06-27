<?php

namespace App\Filament\Resources\G003M004BuildingResource\Pages;

use App\Filament\Resources\G003M004BuildingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG003M004Building extends EditRecord
{
    protected static string $resource = G003M004BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
