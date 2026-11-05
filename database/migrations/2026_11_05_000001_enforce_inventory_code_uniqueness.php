<?php

use App\Services\InventoryIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'g002_m007_items' => 'inventory_master_code_unique',
        'g002_m015_item_instances' => 'inventory_instance_code_unique',
    ];

    public function up(): void
    {
        // Check BOTH tables before any DDL. Never rename/rewrite existing labels.
        // Stop writes during upgrade; MySQL unique index remains the final race guard.
        if (app(InventoryIdentityService::class)->conflicts() !== []) {
            throw new RuntimeException('Duplikasi kode inventaris. Jalankan invman:audit-inventory-identities pada clone; rekonsiliasi identitas/QR dengan pemilik aset sebelum retry. Tidak ada kode yang diubah.');
        }
        foreach (self::INDEXES as $table => $index) {
            // Retry-safe if an earlier MySQL DDL step committed before interruption.
            if (! Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique('code', $index));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $index) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
            }
        }
    }
};
