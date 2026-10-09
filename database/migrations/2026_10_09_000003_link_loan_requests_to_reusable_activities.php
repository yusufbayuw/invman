<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            $table->foreignUuid('related_activity_id')
                ->nullable()
                ->after('id')
                ->constrained('g004_m008_activities')
                ->restrictOnDelete();
            $table->index(['g001_m001_unit_id', 'related_activity_id'], 'loan_activity_unit_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            $table->dropIndex('loan_activity_unit_group_idx');
            $table->dropConstrainedForeignId('related_activity_id');
        });
    }
};
