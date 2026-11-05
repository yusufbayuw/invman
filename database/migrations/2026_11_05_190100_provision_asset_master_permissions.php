<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve fresh-install ordering: RoleSeeder supplies roles on a new database.
        if (! Role::query()->whereIn('name', ['admin', 'fasilitas', 'sarpras'])->exists()) {
            return;
        }
        $facility = Role::query()->where('name', 'fasilitas')->where('guard_name', 'web')->first();
        $sarpras = Role::query()->where('name', 'sarpras')->where('guard_name', 'web')->first();
        $manager = Role::query()->firstOrCreate(['name' => 'pengelola_barang', 'guard_name' => 'web']);
        $masters = [
            'g002::m002::item::type', 'g002::m003::item::management',
            'g002::m007::item', 'g002::m015::item::instance',
            'g003::m004::building', 'g003::m005::floor', 'g003::m006::room',
            'g008::m017::vehicle', 'g008::m018::driver',
        ];
        foreach ($masters as $slug) {
            foreach (['view_any', 'view', 'create', 'update'] as $verb) {
                $permission = Permission::query()->firstOrCreate([
                    'name' => "{$verb}_{$slug}", 'guard_name' => 'web',
                ]);
                if ($facility) {
                    $facility->givePermissionTo($permission);
                }
                if (in_array($slug, ['g002::m007::item', 'g002::m015::item::instance'], true)) {
                    $manager->givePermissionTo($permission);
                    if ($sarpras) {
                        $sarpras->givePermissionTo($permission);
                    }
                }
            }
        }
        \App\Models\User::query()->whereHas('itemManagements')->get()->each(
            fn ($user) => $user->assignRole($manager),
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Access changes may have been customized after rollout. Never delete roles or permissions.
    }
};
