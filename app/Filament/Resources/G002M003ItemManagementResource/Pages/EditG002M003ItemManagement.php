<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG002M003ItemManagement extends EditRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function afterSave(): void
    {
        // Filament has already persisted the users pivot at this lifecycle hook.
        $role = config('role.pengelola_barang');
        if (! \Spatie\Permission\Models\Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
            return;
        }
        $this->record->users()->get()->each(fn ($user) => $user->assignRole($role));
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
