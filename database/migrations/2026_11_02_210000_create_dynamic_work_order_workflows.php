<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_job_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_order_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('work_order_job_title_id')->constrained('work_order_job_titles')->restrictOnDelete();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->restrictOnDelete();
            $table->foreignId('g002_m003_item_management_id')->nullable()->constrained('g002_m003_item_management')->restrictOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
            $table->index(['work_order_job_title_id', 'g001_m001_unit_id'], 'wo_assignment_title_unit');
        });

        Schema::create('work_order_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->json('steps');
            $table->foreignId('assign_job_title_id')->nullable()->constrained('work_order_job_titles')->restrictOnDelete();
            $table->foreignId('close_job_title_id')->nullable()->constrained('work_order_job_titles')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['g001_m001_unit_id', 'status', 'version'], 'wo_workflow_scope_status');
        });

        Schema::create('work_order_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('work_order_workflow_id')->constrained('work_order_workflows')->restrictOnDelete();
            $table->string('step_key', 60);
            $table->string('label', 150);
            $table->string('checkpoint', 24);
            $table->string('resolver', 32);
            $table->foreignId('job_title_id')->nullable()->constrained('work_order_job_titles')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('candidate_user_ids');
            $table->unsignedInteger('sequence');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'step_key'], 'wo_approval_ticket_step');
            $table->index(['ticket_id', 'checkpoint', 'sequence'], 'wo_approval_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_approvals');
        Schema::dropIfExists('work_order_workflows');
        Schema::dropIfExists('work_order_assignments');
        Schema::dropIfExists('work_order_job_titles');
    }
};
