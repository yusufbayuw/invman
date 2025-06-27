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
        Schema::create('g005_m016_item_reservation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('g005_m009_item_reservation_id')->nullable()->constrained('g005_m009_item_reservations')->cascadeOnDelete();
            $table->foreignId('g002_m015_item_instance_id')->nullable()->constrained('g002_m015_item_instances')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g005_m016_item_reservation_details');
    }
};
