<?php

namespace App\Filament\Resources\G009M023RoomChecklistResource\Pages;

use App\Filament\Resources\G009M023RoomChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG009M023RoomChecklists extends ListRecords
{
    protected static string $resource = G009M023RoomChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
