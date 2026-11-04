<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('version');
            $table->string('execution_mode', 16);
            $table->string('vendor_name', 200)->nullable();
            $table->text('diagnosis');
            $table->json('materials');
            $table->decimal('amount', 14, 0);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'version']);
        });
        Schema::table('work_order_approvals', function (Blueprint $table): void {
            $table->foreignId('estimate_id')->nullable()->constrained('work_order_estimates')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('work_order_estimates')->exists()) {
            throw new RuntimeException('Estimasi sudah digunakan. Gunakan forward-fix atau restore konsisten.');
        }
        Schema::table('work_order_approvals', fn (Blueprint $table) => $table->dropConstrainedForeignId('estimate_id'));
        Schema::dropIfExists('work_order_estimates');
    }
};
