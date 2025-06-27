<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG002M003ItemManagement extends ViewRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
