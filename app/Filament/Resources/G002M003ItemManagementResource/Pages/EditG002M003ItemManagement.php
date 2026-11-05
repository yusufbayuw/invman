<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use App\Services\AssetManagerRoleSynchronizer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M003ItemManagement extends EditRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    /** @var list<int> */
    private array $membersBeforeSave = [];

    protected function beforeSave(): void
    {
        $this->membersBeforeSave = $this->record->users()
            ->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
    }

    protected function afterSave(): void
    {
        // Include removed members: revocation requires checking their remaining groups.
        $ids = array_merge(
            $this->membersBeforeSave,
            $this->record->users()->pluck('users.id')->all(),
        );
        app(AssetManagerRoleSynchronizer::class)->syncUserIds($ids);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
