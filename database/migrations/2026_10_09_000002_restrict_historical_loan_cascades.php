<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace legacy cascading deletes with RESTRICT on transactional records
     * and the master-data hierarchy from which they are reachable.
     *
     * Does not remove or rewrite any existing record.
     */
    private const PROTECTED_KEYS = [
        'g004_m008_activities' => ['user_id', 'g001_m001_unit_id'],
        'g005_m009_item_reservations' => ['g004_m008_activity_id', 'g002_m007_item_id'],
        'g005_m010_room_reservations' => ['g004_m008_activity_id', 'g003_m006_room_id'],
        'g005_m019_vehicle_reservations' => ['g004_m008_activity_id'],
        'g005_m016_item_reservation_details' => ['g005_m009_item_reservation_id', 'g002_m015_item_instance_id'],
        'loan_request_checklists' => ['g004_m008_activity_id', 'user_id'],
        'loan_request_reviews' => ['g004_m008_activity_id', 'user_id'],
        'g002_m007_items' => [
            'g001_m001_unit_id', 'g002_m003_item_management_id',
            'g002_m002_item_type_id', 'g003_m006_room_id',
        ],
        'g002_m015_item_instances' => ['g002_m007_item_id', 'g003_m006_room_id', 'g001_m001_unit_id'],
        'g003_m006_rooms' => ['g003_m005_floor_id', 'g001_m001_unit_id'],
        'g003_m005_floors' => ['g003_m004_building_id'],
        'g007_m013_item_histories' => ['g002_m015_item_instance_id', 'user_id'],
        'g007_m014_room_histories' => ['g003_m006_room_id', 'user_id'],
        'g007_m021_vehicle_histories' => ['g008_m017_vehicle_id', 'user_id'],
        'g006_m011_item_reviews' => ['g002_m015_item_instance_id', 'g005_m009_item_reservation_id', 'user_id'],
        'g006_m012_room_reviews' => ['g005_m010_room_reservation_id', 'g003_m006_room_id', 'user_id'],
        'g006_m020_vehicle_reviews' => ['g008_m017_vehicle_id', 'user_id', 'g004_m008_activity_id', 'g005_m019_vehicle_reservation_id'],
    ];

    public function up(): void
    {
        $this->apply('restrict');
    }

    public function down(): void
    {
        // Revert schema rules only; never delete transaction data during rollback.
        $this->apply('cascade');
    }

    private function apply(string $onDelete): void
    {
        // SQLite cannot DROP an existing named FK in-place. Production
        // MySQL/MariaDB uses the restricted constraints; local SQLite uses
        // application guards and the MySQL CI integration test.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::PROTECTED_KEYS as $table => $columns) {
            $constraints = collect(Schema::getForeignKeys($table))
                ->filter(fn (array $key): bool => count($key['columns'] ?? []) === 1
                    && in_array($key['columns'][0], $columns, true))
                ->values()
                ->all();

            if (count($constraints) !== count($columns)) {
                throw new \RuntimeException("Foreign key yang harus dilindungi tidak lengkap: {$table}");
            }

            // Separate DROP and ADD for MySQL/MariaDB and SQLite compatibility.
            Schema::table($table, function (Blueprint $blueprint) use ($constraints): void {
                foreach ($constraints as $constraint) {
                    $blueprint->dropForeign($constraint['name']);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($constraints, $onDelete): void {
                foreach ($constraints as $constraint) {
                    $foreign = $blueprint
                        ->foreign($constraint['columns'][0], $constraint['name'])
                        ->references($constraint['foreign_columns'][0])
                        ->on($constraint['foreign_table']);

                    if ($onDelete === 'cascade') {
                        $foreign->cascadeOnDelete();
                    } else {
                        $foreign->restrictOnDelete();
                    }
                }
            });
        }
    }
};
