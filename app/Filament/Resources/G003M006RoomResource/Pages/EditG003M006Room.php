<?php

namespace App\Filament\Resources\G003M006RoomResource\Pages;

use App\Filament\Resources\G003M006RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG003M006Room extends EditRecord
{
    protected static string $resource = G003M006RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
