<?php

namespace App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG009M022ItemInstanceChecklist extends EditRecord
{
    protected static string $resource = G009M022ItemInstanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
