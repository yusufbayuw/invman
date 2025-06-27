<?php

namespace App\Filament\Resources\G007M014RoomHistoryResource\Pages;

use App\Filament\Resources\G007M014RoomHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG007M014RoomHistory extends EditRecord
{
    protected static string $resource = G007M014RoomHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
