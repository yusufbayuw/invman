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
        Schema::table('g002_m015_item_instances', function (Blueprint $table) {
            $table->boolean('is_borrowable')->after('is_available')->nullable());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g002_m015_item_instances', function (Blueprint $table) {
            $table->dropColumn('is_borrowable');
        });
    }
};
