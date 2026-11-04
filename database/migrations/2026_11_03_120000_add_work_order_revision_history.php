<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_approvals', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('superseded_at')->nullable();
            $table->dropUnique('wo_approval_ticket_step');
            $table->unique(['ticket_id', 'step_key', 'revision'], 'wo_approval_ticket_step_revision');
        });
        Schema::create('work_order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('rejected_approval_id')->unique()->constrained('work_order_approvals')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('checkpoint', 24);
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('work_order_revisions')->exists()) {
            throw new RuntimeException('Riwayat revisi sudah digunakan. Bekukan penulisan dan lakukan forward-fix atau restore konsisten.');
        }
        Schema::dropIfExists('work_order_revisions');
        Schema::table('work_order_approvals', function (Blueprint $table): void {
            $table->dropUnique('wo_approval_ticket_step_revision');
            $table->dropColumn(['revision', 'superseded_at']);
            $table->unique(['ticket_id', 'step_key'], 'wo_approval_ticket_step');
        });
    }
};
