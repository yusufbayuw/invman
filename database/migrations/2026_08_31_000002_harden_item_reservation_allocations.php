<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('g005_m016_item_reservation_details')
            ->select('g005_m009_item_reservation_id', 'g002_m015_item_instance_id')
            ->whereNotNull('g005_m009_item_reservation_id')
            ->whereNotNull('g002_m015_item_instance_id')
            ->groupBy('g005_m009_item_reservation_id', 'g002_m015_item_instance_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate): void {
                $ids = DB::table('g005_m016_item_reservation_details')
                    ->where('g005_m009_item_reservation_id', $duplicate->g005_m009_item_reservation_id)
                    ->where('g002_m015_item_instance_id', $duplicate->g002_m015_item_instance_id)
                    ->orderBy('id')
                    ->pluck('id');

                DB::table('g005_m016_item_reservation_details')
                    ->whereIn('id', $ids->skip(1))
                    ->delete();
            });

        Schema::table('g005_m016_item_reservation_details', function (Blueprint $table) {
            $table->unique(
                ['g005_m009_item_reservation_id', 'g002_m015_item_instance_id'],
                'item_reservation_instance_unique',
            );
        });

        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->index(
                ['g002_m007_item_id', 'status', 'end_time'],
                'item_reservation_open_usage_index',
            );
        });

        Schema::table('g005_m010_room_reservations', function (Blueprint $table) {
            $table->index(
                ['g003_m006_room_id', 'status', 'end_time'],
                'room_reservation_open_usage_index',
            );
        });

        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->index(
                ['g008_m017_vehicle_id', 'status', 'end_time'],
                'vehicle_reservation_open_usage_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table) {
            $table->dropIndex('vehicle_reservation_open_usage_index');
        });

        Schema::table('g005_m010_room_reservations', function (Blueprint $table) {
            $table->dropIndex('room_reservation_open_usage_index');
        });

        Schema::table('g005_m009_item_reservations', function (Blueprint $table) {
            $table->dropIndex('item_reservation_open_usage_index');
        });

        Schema::table('g005_m016_item_reservation_details', function (Blueprint $table) {
            $table->dropUnique('item_reservation_instance_unique');
        });
    }
};
