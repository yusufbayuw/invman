<?php

namespace App\Filament\Resources\G003M005FloorResource\Pages;

use App\Filament\Resources\G003M005FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG003M005Floors extends ListRecords
{
    protected static string $resource = G003M005FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
