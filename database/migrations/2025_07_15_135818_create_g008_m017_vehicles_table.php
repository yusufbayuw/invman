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
        Schema::create('g008_m017_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('license_plate')->nullable();
            $table->date('stnk_date')->nullable();
            $table->date('kir_date')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_borrowable')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g008_m017_vehicles');
    }
};
