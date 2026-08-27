<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = collect([
            config('role.admin'),
            config('role.fasilitas'),
            config('role.sarpras'),
        ])->mapWithKeys(fn (string $name) => [
            $name => Role::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]),
        ]);

        $activity = 'g004::m008::activity';
        $itemReservation = 'g005::m009::item::reservation';
        $roomReservation = 'g005::m010::room::reservation';
        $vehicleReservation = 'g005::m019::vehicle::reservation';

        $facilityPermissions = [
            "view_any_{$activity}", "view_{$activity}", "create_{$activity}", "update_{$activity}",
            "view_any_{$itemReservation}", "view_{$itemReservation}", "create_{$itemReservation}", "update_{$itemReservation}", "delete_{$itemReservation}",
            "view_any_{$roomReservation}", "view_{$roomReservation}", "create_{$roomReservation}", "update_{$roomReservation}", "delete_{$roomReservation}",
            "view_any_{$vehicleReservation}", "view_{$vehicleReservation}", "create_{$vehicleReservation}", "update_{$vehicleReservation}", "delete_{$vehicleReservation}",
            'page_AjukanPeminjaman',
        ];

        $sarprasPermissions = [
            "view_any_{$activity}", "view_{$activity}", "create_{$activity}", "update_{$activity}",
            "view_any_{$itemReservation}", "view_{$itemReservation}",
            "view_any_{$roomReservation}", "view_{$roomReservation}",
            "view_any_{$vehicleReservation}", "view_{$vehicleReservation}",
            'page_AjukanPeminjaman',
        ];

        $permissions = collect($facilityPermissions)->merge($sarprasPermissions)->unique()
            ->mapWithKeys(fn (string $name) => [
                $name => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            ]);

        $roles[config('role.fasilitas')]->syncPermissions($permissions->only($facilityPermissions)->values());
        $roles[config('role.sarpras')]->syncPermissions($permissions->only($sarprasPermissions)->values());
        $roles[config('role.admin')]->syncPermissions(Permission::query()->get());

        Role::query()->where('name', 'unit')->first()?->users?->each(
            fn ($user) => $user->assignRole(config('role.sarpras')),
        );
        Role::query()->where('name', 'super_admin')->first()?->users?->each(
            fn ($user) => $user->assignRole(config('role.admin')),
        );

        Role::query()->whereIn('name', ['unit', 'super_admin'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
