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
        Schema::create('g005_m010_room_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('g004_m008_activity_id')->nullable()->constrained('g004_m008_activities')->cascadeOnDelete();
            $table->foreignId('g003_m006_room_id')->nullable()->constrained('g003_m006_rooms')->cascadeOnDelete();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g005_m010_room_reservations');
    }
};
