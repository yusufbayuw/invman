<?php

namespace App\Filament\Resources\G003M005FloorResource\Pages;

use App\Filament\Resources\G003M005FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG003M005Floor extends EditRecord
{
    protected static string $resource = G003M005FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
