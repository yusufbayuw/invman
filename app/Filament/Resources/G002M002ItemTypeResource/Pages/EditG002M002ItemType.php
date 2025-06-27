<?php

namespace App\Filament\Resources\G002M002ItemTypeResource\Pages;

use App\Filament\Resources\G002M002ItemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M002ItemType extends EditRecord
{
    protected static string $resource = G002M002ItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
