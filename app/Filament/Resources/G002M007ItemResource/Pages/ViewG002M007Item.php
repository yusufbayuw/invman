<?php

namespace App\Filament\Resources\G002M007ItemResource\Pages;

use App\Filament\Resources\G002M007ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG002M007Item extends ViewRecord
{
    protected static string $resource = G002M007ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
