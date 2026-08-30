<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g002_m003_item_management_user')) {
            Schema::create('g002_m003_item_management_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('g002_m003_item_management_id');
                $table->foreign('g002_m003_item_management_id', 'item_management_user_management_fk')
                    ->references('id')
                    ->on('g002_m003_item_management')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['g002_m003_item_management_id', 'user_id'], 'item_management_user_unique');
            });
        }

        if (! Schema::hasColumn('g003_m006_rooms', 'g002_m003_item_management_id')) {
            Schema::table('g003_m006_rooms', function (Blueprint $table) {
                $table->foreignId('g002_m003_item_management_id')->nullable()->after('g001_m001_unit_id')
                    ->constrained('g002_m003_item_management')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('g008_m017_vehicles', 'g002_m003_item_management_id')) {
            Schema::table('g008_m017_vehicles', function (Blueprint $table) {
                $table->foreignId('g002_m003_item_management_id')->nullable()->after('g001_m001_unit_id')
                    ->constrained('g002_m003_item_management')->nullOnDelete();
            });
        }

        foreach (['g005_m009_item_reservations', 'g005_m010_room_reservations', 'g005_m019_vehicle_reservations'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'status_changed_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('status_changed_by')->nullable()->after('status')
                        ->constrained('users')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn($tableName, 'status_changed_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dateTime('status_changed_at')->nullable()->after('status_changed_by');
                });
            }
        }

        // A failed MySQL DDL statement can leave this new table behind without
        // its foreign keys. It has no valid state until this migration succeeds.
        Schema::dropIfExists('loan_reservation_status_histories');

        Schema::create('loan_reservation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_type', 20);
            $table->uuid('reservation_id');
            $table->foreignUuid('g004_m008_activity_id')->nullable();
            $table->foreign('g004_m008_activity_id', 'reservation_history_activity_fk')
                ->references('id')
                ->on('g004_m008_activities')
                ->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['reservation_type', 'reservation_id'], 'reservation_status_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_reservation_status_histories');

        foreach (['g005_m009_item_reservations', 'g005_m010_room_reservations', 'g005_m019_vehicle_reservations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('status_changed_by');
                $table->dropColumn('status_changed_at');
            });
        }

        Schema::table('g008_m017_vehicles', fn (Blueprint $table) => $table->dropConstrainedForeignId('g002_m003_item_management_id'));
        Schema::table('g003_m006_rooms', fn (Blueprint $table) => $table->dropConstrainedForeignId('g002_m003_item_management_id'));
        Schema::dropIfExists('g002_m003_item_management_user');
    }
};
