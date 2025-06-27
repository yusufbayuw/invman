<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG004M008Activities extends ListRecords
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
