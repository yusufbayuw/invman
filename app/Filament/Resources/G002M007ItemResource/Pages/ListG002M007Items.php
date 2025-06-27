<?php

namespace App\Filament\Resources\G002M007ItemResource\Pages;

use App\Filament\Resources\G002M007ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG002M007Items extends ListRecords
{
    protected static string $resource = G002M007ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
