<?php

use App\Services\AssetManagerRoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g002_m003_item_management_user')
            || ! Schema::hasTable('roles')
            || ! Role::query()->where('name', config('role.pengelola_barang'))
                ->where('guard_name', 'web')->exists()) {
            // Fresh installs can still provision the role with RoleSeeder later.
            return;
        }

        app(AssetManagerRoleSynchronizer::class)->reconcileExisting();
    }

    public function down(): void
    {
        // Do not revoke active role grants when rolling back an application migration.
    }
};
