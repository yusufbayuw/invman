<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table) {
            $table->string('status')->default('submitted')->after('attachment')->index();
            $table->text('notes')->nullable()->after('description');
            $table->dateTime('cancelled_at')->nullable()->after('status');
        });

        Schema::table('g005_m010_room_reservations', function (Blueprint $table) {
            $table->dateTime('returned_at')->nullable()->after('end_time');
            $table->index(['g003_m006_room_id', 'start_time', 'end_time'], 'room_reservation_availability_index');
        });

        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->dateTime('returned_at')->nullable()->after('end_time');
            $table->index(['g008_m017_vehicle_id', 'start_time', 'end_time'], 'vehicle_reservation_availability_index');
        });

        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->index(['g002_m007_item_id', 'start_time', 'end_time'], 'item_reservation_availability_index');
        });

        Schema::create('loan_request_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('g004_m008_activity_id')->constrained('g004_m008_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage')->default('return');
            $table->boolean('is_ok')->default(true);
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->unique(['g004_m008_activity_id', 'stage'], 'loan_request_checklist_stage_unique');
        });

        Schema::create('loan_request_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('g004_m008_activity_id')->unique()->constrained('g004_m008_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('review')->nullable();
            $table->timestamps();
        });

        $this->normalizeStatuses('g005_m009_item_reservations');
        $this->normalizeStatuses('g005_m010_room_reservations');
        $this->normalizeStatuses('g005_m019_vehicle_reservations');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_request_reviews');
        Schema::dropIfExists('loan_request_checklists');

        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->dropIndex('item_reservation_availability_index');
        });

        Schema::table('g005_m010_room_reservations', function (Blueprint $table) {
            $table->dropIndex('room_reservation_availability_index');
            $table->dropColumn('returned_at');
        });

        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->dropIndex('vehicle_reservation_availability_index');
            $table->dropColumn('returned_at');
        });

        Schema::table('g004_m008_activities', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'notes', 'cancelled_at']);
        });
    }

    private function normalizeStatuses(string $table): void
    {
        DB::table($table)->whereIn('status', ['menunggu persetujuan', 'menunggu npersetujuan'])->update(['status' => 'submitted']);
        DB::table($table)->where('status', 'disetujui/dipinjamkan')->update(['status' => 'approved']);
        DB::table($table)->whereIn('status', ['dikembalikan', 'tersedia'])->update(['status' => 'returned']);
        DB::table($table)->whereNull('status')->update(['status' => 'submitted']);
    }
};
