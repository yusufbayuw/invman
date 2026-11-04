<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_workflows', function (Blueprint $table): void {
            $table->foreignId('dispatch_job_title_id')->nullable()->constrained('work_order_job_titles')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('work_order_workflows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatch_job_title_id');
        });
    }
};
