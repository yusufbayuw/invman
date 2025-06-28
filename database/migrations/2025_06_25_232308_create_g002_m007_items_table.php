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
        Schema::create('g002_m007_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->cascadeOnDelete();
            $table->foreignId('g002_m003_item_management_id')->nullable()->constrained('g002_m003_item_management')->cascadeOnDelete();
            $table->foreignId('g002_m002_item_type_id')->nullable()->constrained('g002_m002_item_types')->cascadeOnDelete();
            $table->foreignId('g003_m006_room_id')->nullable()->constrained('g003_m006_rooms')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->boolean('is_borrowable')->nullable();
            $table->string('status')->nullable();
            $table->string('quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g002_m007_items');
    }
};
