<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G003M006Room;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketReportingUxTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Kampus A']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Kampus B']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $other = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $other->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Tim A']);
        $manager = User::factory()->create();
        $management->users()->attach($manager);
        $room = G003M006Room::withoutEvents(fn () => G003M006Room::query()->create([
            'name' => 'Ruang Guru', 'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id, 'qr_uuid' => (string) Str::uuid(),
        ]));
        $otherRoom = G003M006Room::withoutEvents(fn () => G003M006Room::query()->create([
            'name' => 'Ruang Rahasia', 'g001_m001_unit_id' => $otherUnit->id,
            'qr_uuid' => (string) Str::uuid(),
        ]));
        return compact('unit', 'otherUnit', 'reporter', 'other', 'facility', 'management', 'manager', 'room', 'otherRoom');
    }

    public function test_short_report_without_inventory_uses_auto_title_and_preserves_reported_urgency(): void
    {
        $f = $this->fixture();
        $category = TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail();
        $service = app(TicketService::class);
        $ticket = $service->open($f['reporter'], [
            'ticket_category_id' => $category->id,
            'location' => 'Ruang Guru lantai dua',
            'description' => 'AC tidak dingin sejak pagi ini.',
            'reported_urgency' => 'emergency',
        ]);
        $this->assertSame('AC & Tata Udara - Ruang Guru lantai dua', $ticket->title);
        $this->assertSame('emergency', $ticket->reported_urgency);
        $this->assertSame('normal', $ticket->priority);
        $this->assertFalse($ticket->assets()->exists());
        $this->assertSame($f['unit']->id, $ticket->g001_m001_unit_id);

        Livewire::actingAs($f['reporter'])->test(CreateTicket::class)
            ->fillForm([
                'ticket_category_id' => $category->id,
                'location' => 'Ruang Guru',
                'description' => 'AC mati ketika ruangan digunakan.',
                'reported_urgency' => 'normal',
                'link_asset' => false,
            ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_search_is_server_side_scoped_and_finds_assets_after_old_250_limit(): void
    {
        $f = $this->fixture();
        $now = now();
        $rows = [];
        for ($i = 1; $i <= 275; $i++) {
            $rows[] = [
                'g001_m001_unit_id' => $f['unit']->id,
                'g002_m003_item_management_id' => $f['management']->id,
                'g003_m006_room_id' => $f['room']->id,
                'name' => sprintf('Peralatan %03d', $i), 'code' => sprintf('IT-%03d', $i),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('g002_m007_items')->insert($rows);
        $lastId = DB::table('g002_m007_items')->insertGetId([
            'g001_m001_unit_id' => $f['unit']->id,
            'g002_m003_item_management_id' => $f['management']->id,
            'g003_m006_room_id' => $f['room']->id,
            'name' => 'Zebra Printer Khusus', 'code' => 'ZB-987',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $foreignId = DB::table('g002_m007_items')->insertGetId([
            'g001_m001_unit_id' => $f['otherUnit']->id,
            'name' => 'Printer Rahasia', 'code' => 'SECRET-22',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $service = app(TicketService::class);
        $this->assertCount(25, $service->assetOptions('item', $f['reporter']));
        $this->assertArrayHasKey($lastId, $service->searchAssets('item', 'ZB-987', null, $f['reporter']));
        $this->assertSame([], $service->searchAssets('item', 'SECRET-22', null, $f['reporter']));
        $this->assertArrayHasKey($foreignId, $service->searchAssets('item', 'SECRET-22', null, $f['facility']));
        $this->assertSame([], $service->searchAssets('item', 'ZB-987', $f['otherRoom']->id, $f['reporter']));
        $this->assertArrayHasKey($lastId, $service->searchAssets('item', 'ZB-987', $f['room']->id, $f['reporter']));
    }

    public function test_manager_can_link_unidentified_asset_with_audit_and_access_guards(): void
    {
        $f = $this->fixture();
        $category = TicketCategory::query()->where('slug', 'maintenance_building')->firstOrFail();
        $service = app(TicketService::class);
        $ticket = $service->open($f['reporter'], [
            'ticket_category_id' => $category->id,
            'location' => 'Ruang Guru',
            'description' => 'Kerusakan plafon membutuhkan pemeriksaan.',
        ]);
        try {
            $service->linkAsset($ticket, $f['other'], 'room', $f['room']->id);
            $this->fail('Foreign reporter linked an asset.');
        } catch (ValidationException) {
            $this->assertFalse($ticket->assets()->exists());
        }
        $linked = $service->linkAsset($ticket, $f['facility'], 'room', $f['room']->id);
        $this->assertSame($f['management']->id, $linked->g002_m003_item_management_id);
        $this->assertDatabaseHas('ticket_asset_links', [
            'ticket_id' => $ticket->id, 'asset_type' => 'room', 'asset_id' => $f['room']->id,
        ]);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'action' => 'asset_linked']);

        $this->expectException(ValidationException::class);
        $service->linkAsset($linked, $f['facility'], 'room', $f['otherRoom']->id);
    }

    public function test_existing_room_qr_offers_scoped_reporting_entry(): void
    {
        $f = $this->fixture();
        $url = route('tickets.report.room', ['room' => $f['room']->qr_uuid]);
        $this->get($url)->assertRedirect();
        $this->actingAs($f['reporter'])->get($url)->assertRedirect(
            TicketResource::getUrl('create', [
                'location' => $f['room']->name, 'asset_type' => 'room', 'asset_id' => $f['room']->id,
            ])
        );
        $this->actingAs($f['other'])->get($url)->assertRedirect(
            TicketResource::getUrl('create', ['location' => $f['room']->name])
        );
    }
    public function test_item_qr_scans_open_prefilled_report_only_for_authorized_users(): void
    {
        $f = $this->fixture();
        $id = DB::table('g002_m015_item_instances')->insertGetId([
            'name' => 'Proyektor Unit A', 'code' => 'PJ-001',
            'g001_m001_unit_id' => $f['unit']->id,
            'g003_m006_room_id' => $f['room']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('tickets.report.item.qr', ['item' => $id]))->assertRedirect();
        $this->actingAs($f['reporter'])->get(route('tickets.report.item.qr', ['item' => $id]))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($f['reporter'])->get(route('tickets.report.item', ['item' => $id]))
            ->assertRedirect(TicketResource::getUrl('create', [
                'location' => 'Ruang Guru', 'asset_type' => 'item_instance', 'asset_id' => $id,
            ]));
        $this->actingAs($f['other'])->get(route('tickets.report.item', ['item' => $id]))
            ->assertForbidden();
        $this->actingAs($f['other'])->get(route('tickets.report.item.qr', ['item' => $id]))
            ->assertForbidden();
    }

}
