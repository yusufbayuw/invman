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
        Schema::create('g009_m024_vehicle_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g008_m017_vehicle_id')->nullable()->constrained('g008_m017_vehicles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->boolean('is_ok')->nullable()->default(false);
            $table->text('notes')->nullable();
            $table->dateTime('checklist_date')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g009_m024_vehicle_checklists');
    }
};
