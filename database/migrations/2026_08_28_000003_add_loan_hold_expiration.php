<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table) {
            $table->dateTime('hold_expires_at')->nullable()->after('cancelled_at')->index();
            $table->dateTime('expired_at')->nullable()->after('hold_expires_at');
        });

        DB::table('g004_m008_activities')
            ->where('status', 'submitted')
            ->whereNull('hold_expires_at')
            ->orderBy('id')
            ->each(function (object $activity): void {
                $deadline = Carbon::parse($activity->created_at)->addHours(config('loans.hold_hours'));

                if ($activity->start_time && Carbon::parse($activity->start_time)->lessThan($deadline)) {
                    $deadline = Carbon::parse($activity->start_time);
                }

                DB::table('g004_m008_activities')
                    ->where('id', $activity->id)
                    ->update([
                        'hold_expires_at' => $deadline,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('g004_m008_activities', function (Blueprint $table) {
            $table->dropIndex(['hold_expires_at']);
            $table->dropColumn(['hold_expires_at', 'expired_at']);
        });
    }
};
