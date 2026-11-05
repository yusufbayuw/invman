<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;

class AssetManagerRoleSynchronizer
{
    /**
     * Keep the technical Shield role aligned with actual asset-group membership.
     * The role gates features; membership continues to restrict records and transactions.
     */
    public function syncUserId(int $userId): void
    {
        $role = Role::query()
            ->where('name', config('role.pengelola_barang'))
            ->where('guard_name', 'web')
            ->first();

        if (! $role || ! $user = User::query()->find($userId)) {
            // RoleSeeder may not have run yet in a fresh database.
            return;
        }

        $hasGroup = $user->itemManagements()->exists();
        $hasRole = $user->hasRole($role);

        if ($hasGroup && ! $hasRole) {
            $user->assignRole($role);
        } elseif (! $hasGroup && $hasRole) {
            $user->removeRole($role);
        }
    }

    /** @param iterable<int|string> $userIds */
    public function syncUserIds(iterable $userIds): void
    {
        $seen = [];

        foreach ($userIds as $id) {
            $userId = (int) $id;

            if ($userId < 1 || isset($seen[$userId])) {
                continue;
            }

            $seen[$userId] = true;
            $this->syncUserId($userId);
        }
    }

    public function reconcileExisting(): void
    {
        $roleName = config('role.pengelola_barang');

        User::query()
            ->whereHas('itemManagements')
            ->orWhereHas('roles', fn ($query) => $query->where('name', $roleName))
            ->chunkById(200, function ($users): void {
                $this->syncUserIds($users->pluck('id'));
            });
    }
}
