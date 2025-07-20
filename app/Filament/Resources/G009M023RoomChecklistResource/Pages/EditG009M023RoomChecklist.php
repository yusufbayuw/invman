<?php

namespace App\Filament\Resources\G009M023RoomChecklistResource\Pages;

use App\Filament\Resources\G009M023RoomChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG009M023RoomChecklist extends EditRecord
{
    protected static string $resource = G009M023RoomChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
