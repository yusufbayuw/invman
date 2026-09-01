<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_request_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->change();
        });

        Schema::table('g006_m011_item_reviews', function (Blueprint $table) {
            $table->foreignUuid('g004_m008_activity_id')
                ->nullable()
                ->after('id')
                ->constrained('g004_m008_activities')
                ->cascadeOnDelete();
            $table->unique(
                ['g004_m008_activity_id', 'g005_m009_item_reservation_id', 'g002_m015_item_instance_id'],
                'item_integrated_review_unique',
            );
        });

        Schema::table('g006_m012_room_reviews', function (Blueprint $table) {
            $table->foreignUuid('g004_m008_activity_id')
                ->nullable()
                ->after('id')
                ->constrained('g004_m008_activities')
                ->cascadeOnDelete();
            $table->unique(
                ['g004_m008_activity_id', 'g005_m010_room_reservation_id'],
                'room_integrated_review_unique',
            );
        });

        Schema::table('g006_m020_vehicle_reviews', function (Blueprint $table) {
            $table->foreignUuid('g004_m008_activity_id')
                ->nullable()
                ->after('id')
                ->constrained('g004_m008_activities')
                ->cascadeOnDelete();
            $table->foreignUuid('g005_m019_vehicle_reservation_id')
                ->nullable()
                ->after('user_id')
                ->constrained('g005_m019_vehicle_reservations')
                ->cascadeOnDelete();
            $table->unique(
                ['g004_m008_activity_id', 'g005_m019_vehicle_reservation_id'],
                'vehicle_integrated_review_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('g006_m020_vehicle_reviews', function (Blueprint $table) {
            $table->dropUnique('vehicle_integrated_review_unique');
            $table->dropForeign(['g005_m019_vehicle_reservation_id']);
            $table->dropForeign(['g004_m008_activity_id']);
            $table->dropColumn(['g005_m019_vehicle_reservation_id', 'g004_m008_activity_id']);
        });

        Schema::table('g006_m012_room_reviews', function (Blueprint $table) {
            $table->dropUnique('room_integrated_review_unique');
            $table->dropForeign(['g004_m008_activity_id']);
            $table->dropColumn('g004_m008_activity_id');
        });

        Schema::table('g006_m011_item_reviews', function (Blueprint $table) {
            $table->dropUnique('item_integrated_review_unique');
            $table->dropForeign(['g004_m008_activity_id']);
            $table->dropColumn('g004_m008_activity_id');
        });

        DB::table('loan_request_reviews')->whereNull('rating')->update(['rating' => 1]);
        Schema::table('loan_request_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable(false)->change();
        });
    }
};
