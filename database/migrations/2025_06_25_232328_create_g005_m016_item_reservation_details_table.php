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
            $table->foreignUuid('g005_m009_item_reservation_id')->nullable();
            $table->foreignId('g002_m015_item_instance_id')->nullable();
            $table->timestamps();

            // Define foreign key with a shorter custom name
            $table->foreign('g005_m009_item_reservation_id', 'fk_item_res_details_to_reservations')
                ->references('id')
                ->on('g005_m009_item_reservations')
                ->cascadeOnDelete();

            // Define foreign key with a shorter custom name
            $table->foreign('g002_m015_item_instance_id', 'fk_item_res_details_to_instances')
                ->references('id')
                ->on('g002_m015_item_instances')
                ->cascadeOnDelete();
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
