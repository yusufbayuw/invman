<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_maintenance_cases', function (Blueprint $table): void {
            $table->foreignUuid('ticket_id')->primary()->constrained('tickets')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('repair_actions')->nullable();
            $table->json('materials')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->timestamp('work_finished_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('final_approved_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('asset_hold_at')->nullable();
            $table->timestamp('asset_released_at')->nullable();
            $table->foreignId('asset_released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('response_escalated_at')->nullable();
            $table->timestamp('resolution_escalated_at')->nullable();
            $table->timestamps();

            $table->index(['response_due_at', 'response_escalated_at'], 'ticket_response_sla_idx');
            $table->index(['resolution_due_at', 'resolution_escalated_at'], 'ticket_resolution_sla_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_maintenance_cases');
    }
};
