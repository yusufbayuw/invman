<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G002M007ItemResource;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\User;
use App\Services\AssetBulkStockService;
use App\Services\AssetRelationService;
use App\Services\LoanAvailabilityService;
use App\Services\LoanCheckoutService;
use App\Services\LoanRequestService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssetStructureAndShieldTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Aset QA']);
        $group = G002M003ItemManagement::query()->create(['name' => 'IT QA']);
        $otherGroup = G002M003ItemManagement::query()->create(['name' => 'Perpustakaan QA']);
        $manager = User::factory()->create();
        $manager->assignRole(config('role.pengelola_barang'));
        $group->users()->attach($manager);
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        return [$unit, $group, $otherGroup, $manager, $facility, $borrower];
    }

    private function item(G001M001Unit $unit, G002M003ItemManagement $group, string $name, int $quantity = 1, string $mode = 'individual'): G002M007Item
    {
        return G002M007Item::query()->create([
            'name' => $name, 'code' => 'QA-'.strtoupper(str_replace(' ', '-', $name)),
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $group->id,
            'tracking_mode' => $mode, 'quantity' => $quantity, 'is_borrowable' => true,
        ]);
    }

    public function test_shield_master_permissions_respect_assigned_asset_manager_group(): void
    {
        [$unit, $group, $otherGroup, $manager, $facility] = $this->fixture();
        $own = $this->item($unit, $group, 'Own laptop');
        $other = $this->item($unit, $otherGroup, 'Other laptop');
        $this->assertTrue($manager->can('viewAny', G002M007Item::class));
        $this->assertTrue($manager->can('view', $own));
        $this->assertFalse($manager->can('view', $other));
        $this->assertFalse($manager->can('update', $other));
        $this->actingAs($manager);
        $this->assertSame([$own->id], G002M007ItemResource::getEloquentQuery()->pluck('id')->all());
        $this->actingAs($facility);
        $this->assertTrue($facility->can('view', $other));
        $this->assertEqualsCanonicalizing([$own->id, $other->id], G002M007ItemResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_bulk_has_no_fake_instances_and_tracks_audited_quantity(): void
    {
        [$unit, $group, , , $facility] = $this->fixture();
        $bulk = $this->item($unit, $group, 'Kursi lipat', 100, 'bulk');
        $this->assertSame(0, $bulk->item_instance()->count());
        $this->assertSame(100, app(LoanAvailabilityService::class)->availableItemQuantity(
            $bulk->id, now()->addHour(), now()->addHours(2),
        ));
        app(AssetBulkStockService::class)->adjust($bulk, -20, 'Pemeriksaan fisik gudang', $facility);
        $this->assertSame(80, (int) $bulk->fresh()->quantity);
        $this->assertSame(80, (int) $bulk->fresh()->available_quantity);
        $this->assertDatabaseHas('asset_bulk_movements', ['item_id' => $bulk->id, 'delta' => -20]);
    }

    public function test_asset_relations_block_cycles_and_cross_management_links(): void
    {
        [$unit, $group, $otherGroup, $manager] = $this->fixture();
        $a = $this->item($unit, $group, 'PC alpha')->item_instance()->firstOrFail();
        $b = $this->item($unit, $group, 'SSD beta')->item_instance()->firstOrFail();
        $c = $this->item($unit, $group, 'UPS gamma')->item_instance()->firstOrFail();
        $external = $this->item($unit, $otherGroup, 'Buku zeta')->item_instance()->firstOrFail();
        $links = app(AssetRelationService::class);
        $links->connect($a->id, $b->id, 'component', $manager);
        $links->connect($b->id, $c->id, 'contained', $manager);
        try {
            $links->connect($c->id, $a->id, 'component', $manager);
            $this->fail('Cycles must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('asset_relations', 2);
        }
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $links->connect($a->id, $external->id, 'component', $manager);
    }

    public function test_bulk_handover_and_return_do_not_allocate_units(): void
    {
        [$unit, $group, , $manager, , $borrower] = $this->fixture();
        $item = $this->item($unit, $group, 'Kursi rapat', 25, 'bulk');
        $activity = G004M008Activity::query()->create([
            'name' => 'Pelatihan QA', 'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'status' => ReservationStatus::Approved->value,
            'start_time' => now()->addHours(2), 'end_time' => now()->addHours(4),
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id, 'quantity' => 10,
            'status' => ReservationStatus::Approved->value,
            'start_time' => $activity->start_time, 'end_time' => $activity->end_time,
        ]);

        $this->actingAs($manager);
        app(LoanCheckoutService::class)->begin('item', $reservation->id, ['is_ok' => true]);
        $this->assertDatabaseHas('loan_checkout_checklists', [
            'reservation_type' => 'item', 'reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => null, 'is_ok' => true,
        ]);
        $this->actingAs($borrower);
        app(LoanCheckoutService::class)->confirm('item', $reservation->id);
        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertSame(0, $reservation->item_reservation_detail()->count());
        app(LoanRequestService::class)->requestReservationReturn('item', $reservation->id, ['is_ok' => true]);
        $this->actingAs($manager);
        app(LoanRequestService::class)->confirmReturn('item', $reservation->id);
        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertSame(25, (int) $item->fresh()->quantity);
    }
}
