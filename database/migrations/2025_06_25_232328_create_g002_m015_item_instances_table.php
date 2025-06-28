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
        Schema::create('g002_m015_item_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('g002_m007_item_id')->nullable()->constrained('g002_m007_items')->cascadeOnDelete();
            $table->foreignId('g003_m006_room_id')->nullable()->constrained('g003_m006_rooms')->cascadeOnDelete();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_available')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g002_m015_item_instances');
    }
};
