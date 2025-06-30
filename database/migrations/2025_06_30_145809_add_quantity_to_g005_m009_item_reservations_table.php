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
        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('g002_m007_item_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
