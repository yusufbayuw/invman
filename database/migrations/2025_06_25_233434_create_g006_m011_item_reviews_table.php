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
        Schema::create('g006_m011_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g002_m015_item_instance_id')->nullable()->constrained('g002_m015_item_instances')->cascadeOnDelete();
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
        Schema::dropIfExists('g006_m011_item_reviews');
    }
};
