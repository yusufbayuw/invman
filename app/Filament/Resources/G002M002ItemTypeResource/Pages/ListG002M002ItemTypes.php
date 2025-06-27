<?php

namespace App\Filament\Resources\G002M002ItemTypeResource\Pages;

use App\Filament\Resources\G002M002ItemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG002M002ItemTypes extends ListRecords
{
    protected static string $resource = G002M002ItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
