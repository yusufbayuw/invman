<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('g009_m022_item_instance_checklists')
            ->whereNull('checklist_date')
            ->whereNull('user_id')
            ->update(['is_ok' => null]);

        DB::table('g009_m022_item_instance_checklists')
            ->select('g002_m015_item_instance_id', 'date')
            ->whereNotNull('g002_m015_item_instance_id')
            ->whereNotNull('date')
            ->groupBy('g002_m015_item_instance_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate): void {
                $rows = DB::table('g009_m022_item_instance_checklists')
                    ->where('g002_m015_item_instance_id', $duplicate->g002_m015_item_instance_id)
                    ->where('date', $duplicate->date)
                    ->get()
                    ->sortByDesc(fn ($row): string => sprintf(
                        '%d-%s-%020d',
                        $row->checklist_date !== null ? 1 : 0,
                        $row->updated_at ?? '',
                        PHP_INT_MAX - (int) $row->id,
                    ));

                DB::table('g009_m022_item_instance_checklists')
                    ->whereIn('id', $rows->skip(1)->pluck('id'))
                    ->delete();
            });

        Schema::table('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->boolean('is_ok')->nullable()->default(null)->change();
            $table->unique(
                ['g002_m015_item_instance_id', 'date'],
                'item_instance_checklist_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->dropUnique('item_instance_checklist_period_unique');
        });

        DB::table('g009_m022_item_instance_checklists')
            ->whereNull('is_ok')
            ->update(['is_ok' => false]);

        Schema::table('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->boolean('is_ok')->nullable()->default(false)->change();
        });
    }
};
