<?php

namespace App\Filament\Resources\G007M013ItemHistoryResource\Pages;

use App\Filament\Resources\G007M013ItemHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG007M013ItemHistory extends ViewRecord
{
    protected static string $resource = G007M013ItemHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
