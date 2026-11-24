<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_setting_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('module', 64);
            $table->boolean('previous_enabled');
            $table->boolean('enabled');
            $table->timestamp('created_at');
            $table->index(['module', 'created_at']);
        });
        DB::table('application_settings')->insertOrIgnore(['key' => 'module_settings_lock', 'value' => '1']);
    }

    public function down(): void
    {
        Schema::dropIfExists('module_setting_audits');
    }
};
