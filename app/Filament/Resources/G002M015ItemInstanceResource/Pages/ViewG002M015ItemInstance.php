<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\Pages;

use App\Filament\Resources\G002M015ItemInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG002M015ItemInstance extends ViewRecord
{
    protected static string $resource = G002M015ItemInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
