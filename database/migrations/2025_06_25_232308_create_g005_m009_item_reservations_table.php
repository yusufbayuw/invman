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
        Schema::create('g005_m009_item_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g004_m008_activity_id')->nullable()->constrained('g004_m008_activities')->cascadeOnDelete();
            $table->foreignId('g002_m007_item_id')->nullable()->constrained('g002_m007_items')->cascadeOnDelete();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g005_m009_item_reservations');
    }
};
