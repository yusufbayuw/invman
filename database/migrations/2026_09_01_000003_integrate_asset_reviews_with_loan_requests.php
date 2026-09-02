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

        $this->addNullableForeignUuid(
            'g006_m011_item_reviews',
            'g004_m008_activity_id',
            'g004_m008_activities',
            'item_review_activity_fk',
            'id',
        );

        if (! Schema::hasIndex('g006_m011_item_reviews', 'item_integrated_review_unique')) {
            Schema::table('g006_m011_item_reviews', function (Blueprint $table) {
                $table->unique(
                    ['g004_m008_activity_id', 'g005_m009_item_reservation_id', 'g002_m015_item_instance_id'],
                    'item_integrated_review_unique',
                );
            });
        }

        $this->addNullableForeignUuid(
            'g006_m012_room_reviews',
            'g004_m008_activity_id',
            'g004_m008_activities',
            'room_review_activity_fk',
            'id',
        );

        if (! Schema::hasIndex('g006_m012_room_reviews', 'room_integrated_review_unique')) {
            Schema::table('g006_m012_room_reviews', function (Blueprint $table) {
                $table->unique(
                    ['g004_m008_activity_id', 'g005_m010_room_reservation_id'],
                    'room_integrated_review_unique',
                );
            });
        }

        $this->addNullableForeignUuid(
            'g006_m020_vehicle_reviews',
            'g004_m008_activity_id',
            'g004_m008_activities',
            'vehicle_review_activity_fk',
            'id',
        );
        $this->addNullableForeignUuid(
            'g006_m020_vehicle_reviews',
            'g005_m019_vehicle_reservation_id',
            'g005_m019_vehicle_reservations',
            'vehicle_review_reservation_fk',
            'user_id',
        );

        if (! Schema::hasIndex('g006_m020_vehicle_reviews', 'vehicle_integrated_review_unique')) {
            Schema::table('g006_m020_vehicle_reviews', function (Blueprint $table) {
                $table->unique(
                    ['g004_m008_activity_id', 'g005_m019_vehicle_reservation_id'],
                    'vehicle_integrated_review_unique',
                );
            });
        }
    }

    private function addNullableForeignUuid(
        string $tableName,
        string $column,
        string $referencedTable,
        string $constraint,
        string $after,
    ): void {
        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $after): void {
                $table->uuid($column)->nullable()->after($after);
            });
        }

        if ($this->hasForeignKey($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $constraint): void {
            $table->foreign($column, $constraint)
                ->references('id')
                ->on($referencedTable)
                ->cascadeOnDelete();
        });
    }

    private function hasForeignKey(string $tableName, string $column): bool
    {
        return collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]);
    }

    private function dropForeignKey(string $tableName, string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys($tableName))
            ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]);

        if (! $foreignKey) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
            $table->dropForeign($foreignKey['name']);
        });
    }

    private function dropColumnIfExists(string $tableName, string $column): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    private function dropUniqueIfExists(string $tableName, string $index): void
    {
        if (! Schema::hasIndex($tableName, $index)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($index): void {
            $table->dropUnique($index);
        });
    }

    public function down(): void
    {
        $this->dropUniqueIfExists('g006_m020_vehicle_reviews', 'vehicle_integrated_review_unique');
        $this->dropForeignKey('g006_m020_vehicle_reviews', 'g005_m019_vehicle_reservation_id');
        $this->dropForeignKey('g006_m020_vehicle_reviews', 'g004_m008_activity_id');
        $this->dropColumnIfExists('g006_m020_vehicle_reviews', 'g005_m019_vehicle_reservation_id');
        $this->dropColumnIfExists('g006_m020_vehicle_reviews', 'g004_m008_activity_id');

        $this->dropUniqueIfExists('g006_m012_room_reviews', 'room_integrated_review_unique');
        $this->dropForeignKey('g006_m012_room_reviews', 'g004_m008_activity_id');
        $this->dropColumnIfExists('g006_m012_room_reviews', 'g004_m008_activity_id');

        $this->dropUniqueIfExists('g006_m011_item_reviews', 'item_integrated_review_unique');
        $this->dropForeignKey('g006_m011_item_reviews', 'g004_m008_activity_id');
        $this->dropColumnIfExists('g006_m011_item_reviews', 'g004_m008_activity_id');

        DB::table('loan_request_reviews')->whereNull('rating')->update(['rating' => 1]);
        Schema::table('loan_request_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable(false)->change();
        });
    }
};
