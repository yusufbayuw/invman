<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $reservationTables = [
        'g005_m009_item_reservations',
        'g005_m010_room_reservations',
        'g005_m019_vehicle_reservations',
    ];

    public function up(): void
    {
        foreach ($this->reservationTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dateTime('overdue_notified_at')->nullable()->after('returned_at');
            });
        }

        Schema::create('loan_reservation_checklists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reservation_type', 20);
            $table->uuid('reservation_id');
            $table->foreignUuid('g004_m008_activity_id')->nullable();
            $table->foreign('g004_m008_activity_id', 'loan_checklist_activity_fk')
                ->references('id')->on('g004_m008_activities')->nullOnDelete();
            $table->foreignId('g002_m015_item_instance_id')->nullable();
            $table->foreign('g002_m015_item_instance_id', 'loan_checklist_instance_fk')
                ->references('id')->on('g002_m015_item_instances')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_ok');
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->dateTime('checked_at');
            $table->timestamps();
            $table->index(['reservation_type', 'reservation_id'], 'loan_checklist_reservation_lookup');
            $table->unique(
                ['reservation_type', 'reservation_id', 'g002_m015_item_instance_id'],
                'loan_checklist_reservation_instance_unique',
            );
        });

        Schema::create('loan_handover_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique();
            $table->string('reservation_type', 20);
            $table->uuid('reservation_id');
            $table->foreignUuid('g004_m008_activity_id')->nullable();
            $table->foreign('g004_m008_activity_id', 'loan_receipt_activity_fk')
                ->references('id')->on('g004_m008_activities')->nullOnDelete();
            $table->string('direction', 20)->default('return');
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('borrower_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('borrower_confirmed_at')->nullable();
            $table->foreignId('manager_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('manager_confirmed_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['reservation_type', 'reservation_id', 'direction'], 'loan_receipt_reservation_lookup');
        });

        Schema::create('loan_reservation_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('reservation_type', 20);
            $table->uuid('reservation_id');
            $table->foreignUuid('g004_m008_activity_id')->nullable();
            $table->foreign('g004_m008_activity_id', 'loan_correction_activity_fk')
                ->references('id')->on('g004_m008_activities')->nullOnDelete();
            $table->string('field', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['reservation_type', 'reservation_id'], 'loan_correction_reservation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_reservation_corrections');
        Schema::dropIfExists('loan_handover_receipts');
        Schema::dropIfExists('loan_reservation_checklists');

        foreach ($this->reservationTables as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('overdue_notified_at'));
        }
    }
};
