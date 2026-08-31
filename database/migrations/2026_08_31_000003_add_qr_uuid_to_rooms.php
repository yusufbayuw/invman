<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g003_m006_rooms', function (Blueprint $table) {
            $table->uuid('qr_uuid')->nullable()->unique()->after('id');
        });

        DB::table('g003_m006_rooms')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $room): void {
                DB::table('g003_m006_rooms')
                    ->where('id', $room->id)
                    ->update([
                        'qr_uuid' => (string) Str::uuid(),
                        'qrcode' => null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('g003_m006_rooms', function (Blueprint $table) {
            $table->dropUnique(['qr_uuid']);
            $table->dropColumn('qr_uuid');
        });
    }
};
