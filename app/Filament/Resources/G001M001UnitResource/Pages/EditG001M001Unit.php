<?php

namespace App\Filament\Resources\G001M001UnitResource\Pages;

use App\Filament\Resources\G001M001UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG001M001Unit extends EditRecord
{
    protected static string $resource = G001M001UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
