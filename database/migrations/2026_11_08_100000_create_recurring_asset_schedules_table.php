<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_asset_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_type');
            $table->unsignedBigInteger('asset_id');
            $table->index(['asset_type', 'asset_id']);
            $table->string('name');
            $table->json('weekdays');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_asset_schedules');
    }
};
