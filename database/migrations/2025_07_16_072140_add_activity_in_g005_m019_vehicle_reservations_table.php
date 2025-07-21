<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->foreignUuid('g004_m008_activity_id')
                ->nullable()
                ->constrained('g004_m008_activities')
                ->cascadeOnDelete()
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->dropForeign(['g004_m008_activity_id']);
            $table->dropColumn('g004_m008_activity_id');
        });
    }
};
