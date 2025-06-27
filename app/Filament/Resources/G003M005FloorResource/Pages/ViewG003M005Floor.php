<?php

namespace App\Filament\Resources\G003M005FloorResource\Pages;

use App\Filament\Resources\G003M005FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG003M005Floor extends ViewRecord
{
    protected static string $resource = G003M005FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
