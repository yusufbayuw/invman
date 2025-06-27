<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG004M008Activity extends EditRecord
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
