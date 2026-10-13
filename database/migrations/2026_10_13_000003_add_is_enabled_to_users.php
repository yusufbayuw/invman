<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // All existing accounts stay accessible after the migration.
            // active_status is Chatify presence and must not be repurposed.
            $table->boolean('is_enabled')->default(true)->after('active_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_enabled'));
    }
};
