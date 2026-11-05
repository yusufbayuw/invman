<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\Pages;

use App\Filament\Resources\G002M015ItemInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M015ItemInstance extends EditRecord
{
    protected static string $resource = G002M015ItemInstanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(\App\Services\AssetManagerMutationGuard::class)->instance($data, auth()->user(), $this->record);
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
