<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use App\Services\AssetManagerRoleSynchronizer;
use Filament\Resources\Pages\CreateRecord;

class CreateG002M003ItemManagement extends CreateRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function afterCreate(): void
    {
        // Re-check the relationship after Filament has persisted the selected users.
        app(AssetManagerRoleSynchronizer::class)->syncUserIds(
            $this->record->users()->pluck('users.id'),
        );
    }
}
