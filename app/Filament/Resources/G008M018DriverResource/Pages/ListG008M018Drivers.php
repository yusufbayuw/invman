<?php

namespace App\Filament\Resources\G008M018DriverResource\Pages;

use App\Filament\Resources\G008M018DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG008M018Drivers extends ListRecords
{
    protected static string $resource = G008M018DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
