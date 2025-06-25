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
        Schema::create('g003_m006_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g003_m005_floor_id')->nullable()->constrained('g003_m005_floors')->cascadeOnDelete();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->boolean('is_borrowable')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g003_m006_rooms');
    }
};
