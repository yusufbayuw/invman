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
        Schema::create('g006_m012_room_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g005_m010_room_reservation_id')->nullable()->constrained('g005_m010_room_reservations')->cascadeOnDelete();
            $table->tinyInteger('rating')->nullable();
            $table->text('review')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g006_m012_room_reviews');
    }
};
