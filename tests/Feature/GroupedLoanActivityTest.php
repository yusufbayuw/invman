<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanCepat;
use App\Filament\Pages\RekapanPenggunaan;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\User;
use App\Services\LoanActivityGrouping;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroupedLoanActivityTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(G001M001Unit $unit): User
    {
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));

        return $user;
    }

    private function schedule(): array
    {
        return [
            'start_time' => now()->addDays(3)->startOfHour()->toDateTimeString(),
            'end_time' => now()->addDays(3)->addHours(2)->startOfHour()->toDateTimeString(),
        ];
    }

    public function test_two_quick_requests_share_one_canonical_activity_but_have_independent_reservations(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Bersama']);
        $user = $this->borrower($unit);
        $item = G002M007Item::query()->create(['name' => 'Proyektor', 'quantity' => 2, 'is_borrowable' => true]);
        $vehicle = G008M017Vehicle::query()->create(['name' => 'Mobil', 'is_borrowable' => true]);

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'purpose' => 'Kegiatan sekolah bersama',
                'asset_id' => $item->id,
                ...$this->schedule(),
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $root = G004M008Activity::query()->sole();

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'type' => 'vehicle',
                'activity_mode' => 'existing',
                'existing_activity_id' => $root->id,
                'asset_id' => $vehicle->id,
                'asset_note' => 'Mobil untuk membawa peserta',
                ...$this->schedule(),
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $linked = G004M008Activity::query()->where('related_activity_id', $root->id)->sole();

        $this->assertSame($root->id, $linked->groupRootId());
        $this->assertSame('Kegiatan sekolah bersama', $linked->name);
        $this->assertSame('Mobil untuk membawa peserta', $linked->notes);
        $this->assertSame($unit->id, $linked->g001_m001_unit_id);
        $this->assertSame($user->id, $linked->user_id);
        $this->assertSame(1, $root->item_reservation()->count());
        $this->assertSame(0, $root->vehicle_reservation()->count());
        $this->assertSame(1, $linked->vehicle_reservation()->count());
        $this->assertSame(ReservationStatus::Submitted->value, $root->fresh()->status);
        $this->assertSame(ReservationStatus::Submitted->value, $linked->status);

        $linked->updateQuietly(['status' => ReservationStatus::Returned->value]);
        $this->assertSame(ReservationStatus::Submitted->value, $root->fresh()->status);
        $this->assertSame(ReservationStatus::Returned->value, $linked->fresh()->status);

        $this->actingAs($user)->get(G004M008ActivityResource::getUrl('view', ['record' => $root]))->assertOk();
        $this->actingAs($user)->get(G004M008ActivityResource::getUrl('view', ['record' => $linked]))->assertOk();

        Livewire::actingAs($user)
            ->test(RekapanPenggunaan::class)
            ->filterTable('activity_group', $root->id)
            ->assertCanSeeTableRecords([$root, $linked]);
    }

    public function test_root_picker_only_contains_eligible_unit_activities_not_child_or_draft(): void
    {
        $myUnit = G001M001Unit::query()->create(['name' => 'Unit A']);
        $outsideUnit = G001M001Unit::query()->create(['name' => 'Unit B']);
        $user = $this->borrower($myUnit);
        $other = $this->borrower($outsideUnit);

        $make = fn (User $who, string $name, string $status, ?string $parentId = null): G004M008Activity =>
            G004M008Activity::query()->create([
                'user_id' => $who->id,
                'g001_m001_unit_id' => $who->g001_m001_unit_id,
                'name' => $name,
                'start_time' => now()->addDay(),
                'end_time' => now()->addDay()->addHour(),
                'status' => $status,
                'related_activity_id' => $parentId,
            ]);

        $root = $make($user, 'Induk yang valid', ReservationStatus::Submitted->value);
        $child = $make($user, 'Turunan', ReservationStatus::Submitted->value, $root->id);
        $draft = $make($user, 'Draft', ReservationStatus::Draft->value);
        $outside = $make($other, 'Rahasia Unit B', ReservationStatus::Submitted->value);

        $options = app(LoanActivityGrouping::class)->options($user);
        $this->assertArrayHasKey($root->id, $options);
        $this->assertArrayNotHasKey($child->id, $options);
        $this->assertArrayNotHasKey($draft->id, $options);
        $this->assertArrayNotHasKey($outside->id, $options);
    }

    public function test_service_rejects_forged_other_unit_and_nested_group_references(): void
    {
        $myUnit = G001M001Unit::query()->create(['name' => 'Unit Saya']);
        $foreignUnit = G001M001Unit::query()->create(['name' => 'Unit Asing']);
        $user = $this->borrower($myUnit);
        $outsider = $this->borrower($foreignUnit);
        $foreignActivity = G004M008Activity::query()->create([
            'user_id' => $outsider->id,
            'g001_m001_unit_id' => $foreignUnit->id,
            'name' => 'Kegiatan rahasia',
            'status' => ReservationStatus::Submitted->value,
        ]);
        $this->actingAs($user);
        $this->expectException(ValidationException::class);

        app(LoanRequestService::class)->submit($user, [
            'name' => 'Pengajuan palsu',
            'description' => 'Pengajuan palsu',
            ...$this->schedule(),
            'related_activity_id' => $foreignActivity->id,
            'needs' => [],
        ]);
    }

    public function test_quick_request_cannot_link_to_another_unit_even_when_id_is_forged(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit sendiri']);
        $foreign = G001M001Unit::query()->create(['name' => 'Unit lain']);
        $user = $this->borrower($unit);
        $foreignUser = $this->borrower($foreign);
        $root = G004M008Activity::query()->create([
            'user_id' => $foreignUser->id,
            'g001_m001_unit_id' => $foreign->id,
            'name' => 'Agenda rahasia',
            'status' => ReservationStatus::Submitted->value,
        ]);
        $item = G002M007Item::query()->create(['name' => 'Speaker', 'quantity' => 1, 'is_borrowable' => true]);

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'activity_mode' => 'existing',
                'existing_activity_id' => $root->id,
                'asset_id' => $item->id,
                ...$this->schedule(),
            ])
            ->call('submit')
            ->assertHasErrors();

        $this->assertSame(1, G004M008Activity::query()->count());
        $this->assertSame(0, $root->linkedRequests()->count());
    }
}
