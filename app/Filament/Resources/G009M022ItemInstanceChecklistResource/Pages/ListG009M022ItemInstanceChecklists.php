<?php

namespace App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG009M022ItemInstanceChecklists extends ListRecords
{
    protected static string $resource = G009M022ItemInstanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
