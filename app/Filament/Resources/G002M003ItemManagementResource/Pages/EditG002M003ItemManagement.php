<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M003ItemManagement extends EditRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
