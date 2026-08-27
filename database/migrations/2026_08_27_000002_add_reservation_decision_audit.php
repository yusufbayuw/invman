<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'g005_m009_item_reservations',
        'g005_m010_room_reservations',
        'g005_m019_vehicle_reservations',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('decision_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->dateTime('decision_at')->nullable()->after('decision_by');
                $table->text('rejection_reason')->nullable()->after('decision_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('decision_by');
                $table->dropColumn(['decision_at', 'rejection_reason']);
            });
        }
    }
};
