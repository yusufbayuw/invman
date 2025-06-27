<?php

namespace App\Filament\Resources\G007M014RoomHistoryResource\Pages;

use App\Filament\Resources\G007M014RoomHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG007M014RoomHistory extends ViewRecord
{
    protected static string $resource = G007M014RoomHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
