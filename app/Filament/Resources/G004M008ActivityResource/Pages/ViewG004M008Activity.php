<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG004M008Activity extends ViewRecord
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
