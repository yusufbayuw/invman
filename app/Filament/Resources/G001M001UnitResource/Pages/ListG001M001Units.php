<?php

namespace App\Filament\Resources\G001M001UnitResource\Pages;

use App\Filament\Resources\G001M001UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG001M001Units extends ListRecords
{
    protected static string $resource = G001M001UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
