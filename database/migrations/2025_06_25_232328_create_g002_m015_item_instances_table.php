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
            $table->foreignId('g002_m015_item_instance_id')->nullable()->constrained('g002_m015_item_instances')->cascadeOnDelete();
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
