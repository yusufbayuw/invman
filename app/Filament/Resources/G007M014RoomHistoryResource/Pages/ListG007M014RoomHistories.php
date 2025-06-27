<?php

namespace App\Filament\Resources\G007M014RoomHistoryResource\Pages;

use App\Filament\Resources\G007M014RoomHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG007M014RoomHistories extends ListRecords
{
    protected static string $resource = G007M014RoomHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
