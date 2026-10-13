<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('description');
            $table->string('reported_urgency', 16)->nullable()->after('location');
        });

        // Preserve legacy business categories and historical tickets.
        foreach ([
            'Bangunan & Interior' => 'maintenance_building',
            'Listrik & Penerangan' => 'maintenance_electrical',
            'AC & Tata Udara' => 'maintenance_hvac',
            'Air & Sanitasi' => 'maintenance_plumbing',
            'Peralatan & Inventaris' => 'maintenance_equipment',
            'Kendaraan' => 'maintenance_vehicle',
            'Lainnya' => 'maintenance_other',
        ] as $name => $slug) {
            DB::table('ticket_categories')->insertOrIgnore([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['location', 'reported_urgency']);
        });
        // Do not delete categories: they may be referenced by persisted tickets.
    }
};
