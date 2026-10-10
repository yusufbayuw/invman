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
        Schema::create('loan_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('g001_m001_unit_id')->nullable()
                ->constrained('g001_m001_units')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->timestamps();
            $table->index(['g001_m001_unit_id', 'created_at']);
        });

        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            $table->foreignUuid('loan_event_id')->nullable()
                ->constrained('loan_events')->restrictOnDelete();
        });

        // Zero-loss backfill: each old canonical request becomes a master event.
        // All legacy related requests inherit their canonical request's event.
        // Original request IDs, statuses, reservations and audit remain untouched.
        DB::table('g004_m008_activities')
            ->whereNull('related_activity_id')
            ->orderBy('id')
            ->chunk(200, function ($roots): void {
                foreach ($roots as $root) {
                    $id = (string) Str::uuid();
                    DB::table('loan_events')->insert([
                        'id' => $id,
                        'g001_m001_unit_id' => $root->g001_m001_unit_id,
                        'created_by' => $root->user_id,
                        'name' => $root->name ?: 'Kegiatan tanpa nama',
                        'description' => $root->description,
                        'start_time' => $root->start_time,
                        'end_time' => $root->end_time,
                        'created_at' => $root->created_at ?: now(),
                        'updated_at' => $root->updated_at ?: now(),
                    ]);
                    DB::table('g004_m008_activities')->where('id', $root->id)
                        ->update(['loan_event_id' => $id]);
                }
            });

        DB::table('g004_m008_activities')
            ->whereNotNull('related_activity_id')
            ->orderBy('id')
            ->chunk(200, function ($children): void {
                foreach ($children as $child) {
                    $rootEvent = DB::table('g004_m008_activities')
                        ->where('id', $child->related_activity_id)->value('loan_event_id');
                    if (! $rootEvent) {
                        throw new RuntimeException('Pengajuan terkait tanpa kegiatan induk: '.$child->id);
                    }
                    DB::table('g004_m008_activities')->where('id', $child->id)
                        ->update(['loan_event_id' => $rootEvent]);
                }
            });

        // Legacy records now have event references, but nullable is intentional
        // for compatibility with direct seed/import paths and rolling upgrades.
    }

    public function down(): void
    {
        // Reverting this migration intentionally removes only the new grouping
        // schema; historical requests and old related_activity_id are unchanged.
        Schema::table('g004_m008_activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loan_event_id');
        });
        Schema::dropIfExists('loan_events');
    }
};
