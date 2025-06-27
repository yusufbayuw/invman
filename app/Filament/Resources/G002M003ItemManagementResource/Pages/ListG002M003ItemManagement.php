<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG002M003ItemManagement extends ListRecords
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
