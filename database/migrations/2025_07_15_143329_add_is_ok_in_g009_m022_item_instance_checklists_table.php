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
        Schema::table('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->boolean('is_ok')->default(false)->after('checklist_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->dropColumn('is_ok');
        });
    }
};
