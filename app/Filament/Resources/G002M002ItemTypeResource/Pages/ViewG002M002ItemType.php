<?php

namespace App\Filament\Resources\G002M002ItemTypeResource\Pages;

use App\Filament\Resources\G002M002ItemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG002M002ItemType extends ViewRecord
{
    protected static string $resource = G002M002ItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
