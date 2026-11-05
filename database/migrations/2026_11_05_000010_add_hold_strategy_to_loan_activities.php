<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            // Historical requests retain the original submit-time reservation semantics.
            $table->string('hold_strategy', 32)->default('on_submission')->after('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            $table->dropColumn('hold_strategy');
        });
    }
};
