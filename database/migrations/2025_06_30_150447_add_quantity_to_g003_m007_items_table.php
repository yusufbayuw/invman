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
        Schema::table('g002_m007_items', function (Blueprint $table) {
            $table->integer('available_quantity')->after('quantity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g002_m007_items', function (Blueprint $table) {
            $table->dropColumn('available_quantity');
        });
    }
};
