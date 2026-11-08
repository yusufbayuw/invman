<?php

namespace Tests\Feature;

use App\Filament\Resources as R;
use App\Models as M;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceRelationCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $user = M\User::factory()->create();
        $user->assignRole(config('role.admin'));
        $this->actingAs($user);
    }

    public function test_building_floors_have_real_crud_and_cannot_be_moved_to_another_parent(): void
    {
        $building = M\G003M004Building::create(['name' => 'Gedung A']);
        $other = M\G003M004Building::create(['name' => 'Gedung B']);
        $component = Livewire::test(R\G003M004BuildingResource\RelationManagers\FloorRelationManager::class, [
            'ownerRecord' => $building, 'pageClass' => R\G003M004BuildingResource\Pages\EditG003M004Building::class,
        ])->callTableAction('create', data: ['name' => 'Lantai 1', 'g003_m004_building_id' => $other->id])
            ->assertHasNoTableActionErrors();
        $floor = $building->floor()->firstOrFail();
        $this->assertSame('Lantai 1', $floor->name);
        $this->assertSame(0, $other->floor()->count());
        $component->callTableAction('edit', $floor, data: ['name' => 'Lantai baru', 'g003_m004_building_id' => $other->id])
            ->assertHasNoTableActionErrors();
        $this->assertSame($building->id, $floor->fresh()->g003_m004_building_id);
        $this->assertSame('Lantai baru', $floor->fresh()->name);
        $component->callTableAction('delete', $floor)->assertHasNoTableActionErrors();
        $this->assertModelMissing($floor);
    }

    public function test_item_instance_crud_maintains_parent_counts_and_scope(): void
    {
        $item = M\G002M007Item::create(['name' => 'Induk A', 'quantity' => 0, 'is_borrowable' => true]);
        $other = M\G002M007Item::create(['name' => 'Induk B', 'quantity' => 0]);
        $component = Livewire::test(R\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager::class, [
            'ownerRecord' => $item, 'pageClass' => R\G002M007ItemResource\Pages\EditG002M007Item::class,
        ])->callTableAction('create', data: ['name' => 'Unit 1', 'code' => 'TEST-UNIT-001', 'is_borrowable' => true,
            'is_available' => true, 'g002_m007_item_id' => $other->id])->assertHasNoTableActionErrors();
        $instance = $item->item_instance()->firstOrFail();
        $this->assertSame(1, (int) $item->fresh()->quantity);
        $this->assertSame(0, $other->item_instance()->count());
        $component->callTableAction('edit', $instance, data: ['name' => 'Unit Diubah', 'code' => 'TEST-UNIT-001', 'is_borrowable' => false, 'is_available' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame(0, (int) $item->fresh()->available_quantity);
        $component->callTableAction('delete', $instance)->assertHasNoTableActionErrors();
        $this->assertSame(0, (int) $item->fresh()->quantity);
    }

    public function test_bulk_items_and_read_only_parent_pages_cannot_create_instances(): void
    {
        $item = M\G002M007Item::create(['name' => 'Bulk', 'tracking_mode' => 'bulk', 'quantity' => 5]);
        Livewire::test(R\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager::class, [
            'ownerRecord' => $item, 'pageClass' => R\G002M007ItemResource\Pages\EditG002M007Item::class,
        ])->assertTableActionHidden('create');
        $individual = M\G002M007Item::create(['name' => 'Individual', 'quantity' => 0]);
        Livewire::test(R\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager::class, [
            'ownerRecord' => $individual, 'pageClass' => R\G002M007ItemResource\Pages\ViewG002M007Item::class,
        ])->assertTableActionHidden('create');
    }

    public function test_sarpras_and_asset_managers_can_create_only_within_their_scope_without_delete_grants(): void
    {
        foreach (['sarpras', 'manager'] as $kind) {
            $unit = M\G001M001Unit::create(['name' => 'Unit '.$kind]);
            $group = M\G002M003ItemManagement::create(['name' => 'Kelompok '.$kind]);
            $actor = M\User::factory()->create(['g001_m001_unit_id' => $unit->id]);
            if ($kind === 'sarpras') {
                $actor->assignRole(config('role.sarpras'));
            } else {
                $group->users()->attach($actor->id);
            }
            $item = M\G002M007Item::create(['name' => 'Induk '.$kind, 'quantity' => 0, 'g001_m001_unit_id' => $unit->id,
                'g002_m003_item_management_id' => $group->id, 'is_borrowable' => true]);
            $component = Livewire::actingAs($actor)->test(R\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager::class, [
                'ownerRecord' => $item, 'pageClass' => R\G002M007ItemResource\Pages\EditG002M007Item::class,
            ])->callTableAction('create', data: ['name' => 'Satuan '.$kind, 'code' => 'SCOPED-'.$kind, 'is_available' => true, 'is_borrowable' => true])
                ->assertHasNoTableActionErrors();
            $instance = $item->item_instance()->firstOrFail();
            $component->assertTableActionVisible('edit', $instance)->assertTableActionHidden('delete', $instance);
            $this->assertSame($unit->id, $instance->g001_m001_unit_id);
        }
    }

    public function test_membership_changes_synchronize_derived_roles_and_keep_other_groups(): void
    {
        $group = M\G002M003ItemManagement::create(['name' => 'Grup A']);
        $other = M\G002M003ItemManagement::create(['name' => 'Grup B']);
        $member = M\User::factory()->create();
        $this->get(R\UserResource::getUrl('edit', ['record' => $member]))->assertOk();
        $component = Livewire::test(R\RelationManagers\ManagementUsersRelationManager::class, [
            'ownerRecord' => $group, 'pageClass' => R\G002M003ItemManagementResource\Pages\EditG002M003ItemManagement::class,
        ])->callTableAction('addMember', data: ['member_id' => $member->id])->assertHasNoTableActionErrors();
        $this->assertTrue($member->fresh()->hasRole(config('role.pengelola_barang')));
        $other->users()->attach($member->id);
        $component->callTableAction('removeMember', $member)->assertHasNoTableActionErrors();
        $this->assertTrue($member->fresh()->hasRole(config('role.pengelola_barang')));
        $other->users()->detach($member->id);
        $this->assertFalse($member->fresh()->hasRole(config('role.pengelola_barang')));
    }

    public function test_ticket_comment_relations_hide_internal_data_and_preserve_audited_writes(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Pelapor']);
        $reporter = M\User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $category = M\TicketCategory::create(['name' => 'Kategori', 'slug' => 'kategori', 'is_active' => true, 'reporting_enabled' => true]);
        $ticket = app(TicketService::class)->open($reporter, ['ticket_category_id' => $category->id, 'title' => 'Perbaikan ruangan', 'description' => 'Perbaikan lampu ruangan perlu bantuan']);
        $internal = app(TicketService::class)->comment($ticket, auth()->user(), 'Catatan internal rahasia', true);
        $public = app(TicketService::class)->comment($ticket, $reporter, 'Komentar pelapor', false);
        Livewire::actingAs($reporter)->test(R\RelationManagers\TicketVisibleCommentsRelationManager::class, [
            'ownerRecord' => $ticket, 'pageClass' => R\TicketResource\Pages\ViewTicket::class,
        ])->assertCanSeeTableRecords([$public])->assertCanNotSeeTableRecords([$internal])
            ->callTableAction('comment', data: ['body' => 'Komentar melalui relasi'])->assertHasNoTableActionErrors();
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'user_id' => $reporter->id, 'body' => 'Komentar melalui relasi', 'is_internal' => false]);
        Livewire::test(R\RelationManagers\TicketWorkOrderEstimatesRelationManager::class, [
            'ownerRecord' => $ticket, 'pageClass' => R\TicketResource\Pages\ViewTicket::class,
        ])->assertForbidden();
    }

    public function test_draft_relation_crud_cannot_forge_status_owner_schedule_or_duplicate_assets(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit']);
        auth()->user()->update(['g001_m001_unit_id' => $unit->id]);
        $service = app(LoanRequestService::class);
        $start = now()->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHour();
        $draft = $service->saveDraft(auth()->user(), ['name' => 'Draf relasi', 'description' => 'Uji guard relasi', 'start_time' => $start, 'end_time' => $end, 'needs' => []]);
        $room = M\G003M006Room::create(['name' => 'Ruangan', 'is_borrowable' => true]);
        $component = Livewire::test(R\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager::class, [
            'ownerRecord' => $draft, 'pageClass' => R\G004M008ActivityResource\Pages\EditG004M008Activity::class,
        ])->callTableAction('create', data: ['g003_m006_room_id' => $room->id, 'g004_m008_activity_id' => 'forged', 'status' => 'approved'])
            ->assertHasNoTableActionErrors();
        $reservation = $draft->room_reservation()->firstOrFail();
        $this->assertSame('draft', $reservation->status);
        $this->assertTrue($reservation->start_time->equalTo($start));
        $component->callTableAction('create', data: ['g003_m006_room_id' => $room->id])->assertHasTableActionErrors();
        $this->assertSame(1, $draft->room_reservation()->count());
        auth()->user()->assignRole(config('role.sarpras'));
        $service->submitDraft(auth()->user(), $draft);
        Livewire::test(R\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager::class, [
            'ownerRecord' => $draft->fresh(), 'pageClass' => R\G004M008ActivityResource\Pages\EditG004M008Activity::class,
        ])->assertTableActionHidden('create')->assertTableActionHidden('edit', $reservation)->assertTableActionHidden('delete', $reservation);
    }

    public function test_out_of_scope_parent_is_forbidden_for_sarpras_and_regular_users(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit A']);
        $other = M\G001M001Unit::create(['name' => 'Unit B']);
        $item = M\G002M007Item::create(['name' => 'Barang B', 'g001_m001_unit_id' => $other->id, 'quantity' => 0]);
        $user = M\User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $user->assignRole(config('role.sarpras'));
        Livewire::actingAs($user)->test(R\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager::class, [
            'ownerRecord' => $item, 'pageClass' => R\G002M007ItemResource\Pages\EditG002M007Item::class,
        ])->assertForbidden();
        Livewire::actingAs(M\User::factory()->create())->test(R\G003M004BuildingResource\RelationManagers\FloorRelationManager::class, [
            'ownerRecord' => M\G003M004Building::create(['name' => 'Gedung']), 'pageClass' => R\G003M004BuildingResource\Pages\EditG003M004Building::class,
        ])->assertForbidden();
    }
}
