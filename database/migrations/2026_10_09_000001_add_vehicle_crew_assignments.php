<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g008_m017_vehicles', function (Blueprint $table): void {
            $table->foreignId('default_driver_id')->nullable()->after('id')
                ->constrained('g008_m018_drivers')->nullOnDelete();
            $table->boolean('requires_assistant')->default(false)->after('default_driver_id');
        });

        // Legacy drivers.vehicle_default is the inverse relation. Do not change historical
        // columns or infer buses from display names. Lowest ID wins in ambiguous mappings.
        $defaults = DB::table('g008_m018_drivers')
            ->whereNotNull('vehicle_default')
            ->orderBy('id')
            ->get(['id', 'vehicle_default']);
        foreach ($defaults as $driver) {
            DB::table('g008_m017_vehicles')
                ->where('id', $driver->vehicle_default)
                ->whereNull('default_driver_id')
                ->update(['default_driver_id' => $driver->id]);
        }

        // Only explicitly known internal buses are flagged during migration.
        DB::table('g008_m017_vehicles')
            ->whereIn('name', ['BUS 01', 'BUS 02', 'BUS 03'])
            ->update(['requires_assistant' => true]);

        Schema::create('vehicle_assistants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->dropForeign(['g008_m018_driver_id']);
        });
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('g008_m018_driver_id')->nullable()->change();
            $table->foreignId('vehicle_assistant_id')->nullable()->after('g008_m018_driver_id')
                ->constrained('vehicle_assistants')->restrictOnDelete();
        });
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            // Existing history cannot be silently destroyed by deleting a driver.
            $table->foreign('g008_m018_driver_id')->references('id')
                ->on('g008_m018_drivers')->restrictOnDelete();
        });

        // Retain reservations when a vehicle is archived/retired: no cascading delete.
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->dropForeign(['g008_m017_vehicle_id']);
        });
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->foreign('g008_m017_vehicle_id')->references('id')
                ->on('g008_m017_vehicles')->restrictOnDelete();
        });

        Schema::create('vehicle_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('vehicle_reservation_id')
                ->constrained('g005_m019_vehicle_reservations')->restrictOnDelete();
            $table->foreignId('old_driver_id')->nullable()->constrained('g008_m018_drivers')->restrictOnDelete();
            $table->foreignId('new_driver_id')->nullable()->constrained('g008_m018_drivers')->restrictOnDelete();
            $table->foreignId('old_assistant_id')->nullable()->constrained('vehicle_assistants')->restrictOnDelete();
            $table->foreignId('new_assistant_id')->nullable()->constrained('vehicle_assistants')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->text('reason');
            $table->timestamps();
            $table->index(['vehicle_reservation_id', 'created_at'], 'vehicle_assignment_history_lookup');
        });
    }

    public function down(): void
    {
        // Do not leave a half-rolled-back schema if a nullable draft still exists.
        if (DB::table('g005_m019_vehicle_reservations')->whereNull('g008_m018_driver_id')->exists()) {
            throw new \RuntimeException('Tidak dapat rollback: reservasi tanpa pengemudi masih ada.');
        }

        Schema::dropIfExists('vehicle_assignment_histories');
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_assistant_id');
            $table->dropForeign(['g008_m018_driver_id']);
        });

        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('g008_m018_driver_id')->nullable(false)->change();
            $table->foreign('g008_m018_driver_id')->references('id')
                ->on('g008_m018_drivers')->cascadeOnDelete();
        });
        Schema::table('g005_m019_vehicle_reservations', function (Blueprint $table): void {
            $table->dropForeign(['g008_m017_vehicle_id']);
            $table->foreign('g008_m017_vehicle_id')->references('id')
                ->on('g008_m017_vehicles')->cascadeOnDelete();
        });
        Schema::dropIfExists('vehicle_assistants');
        Schema::table('g008_m017_vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_driver_id');
            $table->dropColumn('requires_assistant');
        });
    }
};
