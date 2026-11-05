<?php

namespace App\Filament\Resources\G002M003ItemManagementResource\Pages;

use App\Filament\Resources\G002M003ItemManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateG002M003ItemManagement extends CreateRecord
{
    protected static string $resource = G002M003ItemManagementResource::class;

    protected function afterCreate(): void
    {
        // Filament has already persisted the users pivot at this lifecycle hook.
        $role = config('role.pengelola_barang');
        if (! \Spatie\Permission\Models\Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
            return;
        }
        $this->record->users()->get()->each(fn ($user) => $user->assignRole($role));
    }

}
