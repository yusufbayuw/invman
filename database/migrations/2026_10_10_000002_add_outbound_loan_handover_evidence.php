<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_checkout_checklists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reservation_type', 20);
            $table->uuid('reservation_id');
            $table->foreignUuid('g004_m008_activity_id')->nullable()
                ->constrained('g004_m008_activities')->restrictOnDelete();
            $table->foreignId('g002_m015_item_instance_id')->nullable()
                ->constrained('g002_m015_item_instances')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->boolean('is_ok');
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->dateTime('checked_at');
            $table->timestamps();
            $table->index(['reservation_type', 'reservation_id'], 'loan_checkout_checklists_lookup');
        });

        Schema::table('loan_handover_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('checkout_odometer')->nullable();
            $table->text('fallback_reason')->nullable();
            $table->unique(
                ['reservation_type', 'reservation_id', 'direction'],
                'loan_receipt_one_per_direction',
            );
        });
    }

    public function down(): void
    {
        Schema::table('loan_handover_receipts', function (Blueprint $table): void {
            $table->dropUnique('loan_receipt_one_per_direction');
            $table->dropColumn(['checkout_odometer', 'fallback_reason']);
        });
        Schema::dropIfExists('loan_checkout_checklists');
    }
};
