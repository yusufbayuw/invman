<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\Pages;

use App\Filament\Resources\G002M015ItemInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG002M015ItemInstances extends ListRecords
{
    protected static string $resource = G002M015ItemInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
