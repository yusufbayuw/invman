<?php

namespace App\Filament\Resources\G003M006RoomResource\Pages;

use App\Filament\Resources\G003M006RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG003M006Room extends ViewRecord
{
    protected static string $resource = G003M006RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
