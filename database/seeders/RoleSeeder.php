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
            config('role.pengelola_barang'),
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

        // Asset masters use Shield permissions; data membership is checked separately in policies.
        $masterResources = [
            'g002::m002::item::type', 'g002::m003::item::management',
            'g002::m007::item', 'g002::m015::item::instance',
            'g003::m004::building', 'g003::m005::floor', 'g003::m006::room',
            'g008::m017::vehicle', 'g008::m018::driver',
        ];
        foreach ($masterResources as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $facilityPermissions[] = "{$action}_{$resource}";
            }
        }
        $managerPermissions = [];
        foreach (['g002::m007::item', 'g002::m015::item::instance'] as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $managerPermissions[] = "{$action}_{$resource}";
            }
        }

        $sarprasPermissions = [
            "view_any_{$activity}", "view_{$activity}", "create_{$activity}", "update_{$activity}",
            "view_any_{$itemReservation}", "view_{$itemReservation}",
            "view_any_{$roomReservation}", "view_{$roomReservation}",
            "view_any_{$vehicleReservation}", "view_{$vehicleReservation}",
            'page_AjukanPeminjaman',
        ];

        $permissions = collect($facilityPermissions)->merge($sarprasPermissions)->merge($managerPermissions)->unique()
            ->mapWithKeys(fn (string $name) => [
                $name => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            ]);

        // Bootstrap only empty roles. Never restore permissions later revoked in the Shield UI.
        foreach ([
            config('role.fasilitas') => $facilityPermissions,
            config('role.sarpras') => $sarprasPermissions,
            config('role.pengelola_barang') => $managerPermissions,
        ] as $name => $defaults) {
            if (! $roles[$name]->permissions()->exists()) {
                $roles[$name]->givePermissionTo($permissions->only($defaults)->values());
            }
        }
        if (! $roles[config('role.admin')]->permissions()->exists()) {
            $roles[config('role.admin')]->givePermissionTo(Permission::query()->get());
        }

        // Existing group-linked users retain their assignments, and gain the dedicated role.
        \App\Models\User::query()->whereHas('itemManagements')->get()->each(
            fn ($user) => $user->assignRole(config('role.pengelola_barang')),
        );

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
