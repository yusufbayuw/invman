<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_handling_plans', function (Blueprint $table): void {
            $table->foreignUuid('ticket_id')->primary()->constrained('tickets')->restrictOnDelete();
            $table->string('decision', 24);
            $table->string('execution_mode', 16)->nullable();
            $table->string('vendor_name', 200)->nullable();
            $table->text('vendor_scope')->nullable();
            $table->text('reason');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('vendor_reference', 200)->nullable();
            $table->timestamp('vendor_dispatched_at')->nullable();
            $table->foreignId('vendor_dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('vendor_result')->nullable();
            $table->text('vendor_evidence')->nullable();
            $table->timestamp('vendor_completed_at')->nullable();
            $table->foreignId('vendor_completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('vendor_accepted')->nullable();
            $table->text('vendor_inspection_notes')->nullable();
            $table->timestamp('vendor_inspected_at')->nullable();
            $table->foreignId('vendor_inspected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_material_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('unit', 25);
            $table->decimal('required_quantity', 14, 3);
            $table->decimal('stock_allocated_quantity', 14, 3)->default(0);
            $table->decimal('issued_quantity', 14, 3)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('stock_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('stock_confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'id']);
        });

        Schema::create('work_order_material_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_material_line_id')->constrained('work_order_material_lines')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('status', 24)->default('requested');
            $table->string('vendor_name', 200)->nullable();
            $table->string('order_reference', 200)->nullable();
            $table->decimal('amount', 14, 0)->nullable();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();
            $table->index(['work_order_material_line_id', 'status']);
        });

        Schema::create('work_order_material_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_material_order_id')->constrained('work_order_material_orders')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('accepted_quantity', 14, 3)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('reference', 200);
            $table->text('inspection_notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
            $table->index(['work_order_material_order_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['work_order_material_receipts', 'work_order_material_orders', 'work_order_material_lines', 'work_order_handling_plans'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Histori Work Order sudah digunakan. Gunakan forward-fix atau pemulihan data terencana.');
            }
        }
        Schema::dropIfExists('work_order_material_receipts');
        Schema::dropIfExists('work_order_material_orders');
        Schema::dropIfExists('work_order_material_lines');
        Schema::dropIfExists('work_order_handling_plans');
    }
};
