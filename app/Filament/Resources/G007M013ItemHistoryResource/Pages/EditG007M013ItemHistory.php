<?php

namespace App\Filament\Resources\G007M013ItemHistoryResource\Pages;

use App\Filament\Resources\G007M013ItemHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG007M013ItemHistory extends EditRecord
{
    protected static string $resource = G007M013ItemHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
