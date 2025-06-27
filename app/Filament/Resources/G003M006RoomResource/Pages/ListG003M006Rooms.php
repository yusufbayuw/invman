<?php

namespace App\Filament\Resources\G003M006RoomResource\Pages;

use App\Filament\Resources\G003M006RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG003M006Rooms extends ListRecords
{
    protected static string $resource = G003M006RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
