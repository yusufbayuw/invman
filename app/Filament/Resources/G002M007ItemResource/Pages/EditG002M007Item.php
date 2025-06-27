<?php

namespace App\Filament\Resources\G002M007ItemResource\Pages;

use App\Filament\Resources\G002M007ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M007Item extends EditRecord
{
    protected static string $resource = G002M007ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
