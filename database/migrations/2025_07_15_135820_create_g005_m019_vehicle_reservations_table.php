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
        Schema::create('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('g008_m017_vehicle_id')->nullable()->constrained('g008_m017_vehicles')->cascadeOnDelete();
            $table->foreignId('g008_m018_driver_id')->nullable()->constrained('g008_m018_drivers')->cascadeOnDelete();
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
        Schema::dropIfExists('g005_m019_vehicle_reservations');
    }
};
