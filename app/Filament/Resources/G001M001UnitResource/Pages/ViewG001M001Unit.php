<?php

namespace App\Filament\Resources\G001M001UnitResource\Pages;

use App\Filament\Resources\G001M001UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG001M001Unit extends ViewRecord
{
    protected static string $resource = G001M001UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
