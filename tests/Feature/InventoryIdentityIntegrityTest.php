<?php
namespace Tests\Feature;

use App\Models\{G002M007Item, G002M015ItemInstance};
use App\Services\InventoryIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class InventoryIdentityIntegrityTest extends TestCase
{
    use DatabaseMigrations;

    // Test this migration's down/up explicitly, without rolling back unrelated
    // historical MySQL-only foreign-key migrations on SQLite at teardown.
    public function runDatabaseMigrations()
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_database_rejects_duplicate_master_and_instance_codes_without_changing_totals(): void
    {
        $first = G002M007Item::create(['name' => 'Laptop', 'code' => 'LAP', 'quantity' => 1, 'is_borrowable' => true]);
        try {
            G002M007Item::create(['name' => 'Other Laptop', 'code' => 'LAP', 'quantity' => 2]);
            $this->fail('Duplicate master code accepted');
        } catch (QueryException) {
            $this->assertSame(1, G002M007Item::count());
            $this->assertSame(1, G002M015ItemInstance::count());
        }
        try {
            G002M015ItemInstance::create(['g002_m007_item_id' => $first->id, 'code' => 'LAP-1']);
            $this->fail('Duplicate instance code accepted');
        } catch (QueryException) {
            $this->assertSame(1, (int) $first->fresh()->quantity);
            $this->assertSame(1, (int) $first->fresh()->available_quantity);
        }
        $this->assertSame([], app(InventoryIdentityService::class)->conflicts());
        $this->artisan('invman:audit-inventory-identities')->assertSuccessful();
    }

    public function test_generated_codes_are_global_and_skip_preexisting_physical_labels(): void
    {
        $first = G002M007Item::create(['name' => 'Uncoded first', 'quantity' => 2, 'is_borrowable' => true]);
        $second = G002M007Item::create(['name' => 'Uncoded second', 'quantity' => 2, 'is_borrowable' => true]);
        $this->assertSame(4, G002M015ItemInstance::distinct()->count('code'));
        G002M015ItemInstance::create(['g002_m007_item_id' => $first->id, 'code' => 'OTHER-1']);
        $other = G002M007Item::create(['name' => 'Named code', 'code' => 'OTHER', 'quantity' => 1]);
        $this->assertSame('OTHER-2', $other->item_instance()->first()->code);
        $this->assertSame(2, (int) $second->fresh()->quantity);
    }

    public function test_upgrade_conflicts_stop_before_any_index_or_label_change_and_retry_is_safe(): void
    {
        $migration = require database_path('migrations/2026_11_05_000001_enforce_inventory_code_uniqueness.php');
        $migration->down();
        DB::table('g002_m007_items')->insert([['code' => 'LEGACY'], ['code' => 'LEGACY']]);
        DB::table('g002_m015_item_instances')->insert([['code' => 'QR-LEGACY'], ['code' => 'QR-LEGACY']]);
        $before = DB::table('g002_m015_item_instances')->orderBy('id')->get()->toJson();
        $this->assertCount(2, app(InventoryIdentityService::class)->conflicts());
        $this->artisan('invman:audit-inventory-identities')->assertFailed();
        try {
            $migration->up();
            $this->fail('Conflicting legacy data must block DDL');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Duplikasi kode', $error->getMessage());
            $this->assertFalse(Schema::hasIndex('g002_m007_items', 'inventory_master_code_unique'));
            $this->assertSame($before, DB::table('g002_m015_item_instances')->orderBy('id')->get()->toJson());
        }
        // Synthetic custodian-approved reconciliation; never performed by migration.
        DB::table('g002_m007_items')->where('id', DB::table('g002_m007_items')->max('id'))->update(['code' => 'LEGACY-RECONCILED']);
        DB::table('g002_m015_item_instances')->where('id', DB::table('g002_m015_item_instances')->max('id'))->update(['code' => 'QR-RECONCILED']);
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasIndex('g002_m007_items', 'inventory_master_code_unique'));
        $this->assertTrue(Schema::hasIndex('g002_m015_item_instances', 'inventory_instance_code_unique'));
        $this->assertSame(2, DB::table('g002_m015_item_instances')->count());
    }
}
