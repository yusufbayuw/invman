<?php

namespace App\Filament\Resources\G007M013ItemHistoryResource\Pages;

use App\Filament\Resources\G007M013ItemHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG007M013ItemHistories extends ListRecords
{
    protected static string $resource = G007M013ItemHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
