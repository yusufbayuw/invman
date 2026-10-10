<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Pages\PeminjamanCepat;
use App\Filament\Pages\RekapanPenggunaan;
use App\Filament\Resources\LoanEventResource;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\LoanEvent;
use App\Models\User;
use App\Services\LoanEventService;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanEventMasterTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(G001M001Unit $unit): User
    {
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));

        return $user;
    }

    private function item(): G002M007Item
    {
        return G002M007Item::query()->create([
            'name' => 'Aset Bersama', 'quantity' => 3, 'available_quantity' => 3,
            'is_borrowable' => true,
        ]);
    }

    private function window(int $days = 3): array
    {
        return [
            'start_time' => now()->addDays($days)->startOfHour()->toDateTimeString(),
            'end_time' => now()->addDays($days)->addHours(2)->startOfHour()->toDateTimeString(),
        ];
    }

    public function test_every_request_gets_a_master_event_and_it_outlives_cancelled_requests(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Penguji']);
        $user = $this->borrower($unit);
        $item = $this->item();

        Livewire::actingAs($user)->test(PeminjamanCepat::class)
            ->fillForm(['asset_id' => $item->id, 'purpose' => 'Kemah Sains', ...$this->window()])
            ->call('submit')
            ->assertHasNoFormErrors();

        $first = G004M008Activity::query()->sole();
        $master = LoanEvent::query()->sole();
        $this->assertSame($master->id, $first->loan_event_id);
        $this->assertSame('Kemah Sains', $master->name);

        // A cancelled request cannot erase/disable the master activity.
        app(LoanRequestService::class)->cancel($first);
        $this->assertSame(ReservationStatus::Cancelled->value, $first->fresh()->status);
        $this->assertArrayHasKey($master->id, app(LoanEventService::class)->options($user));

        Livewire::actingAs($user)->test(PeminjamanCepat::class)
            ->fillForm([
                'activity_mode' => 'existing',
                'existing_activity_id' => $master->id,
                'asset_id' => $item->id,
                'asset_note' => 'Membawa alat peraga',
                ...$this->window(5),
            ])
            ->call('submit')->assertHasNoFormErrors();

        $this->assertSame(1, LoanEvent::query()->count());
        $second = G004M008Activity::query()->whereKeyNot($first->id)->sole();
        $this->assertSame($master->id, $second->loan_event_id);
        $this->assertNull($second->related_activity_id);
        $this->assertSame('Membawa alat peraga', $second->notes);
        $this->assertSame(2, $master->requests()->count());

        Livewire::actingAs($user)->test(RekapanPenggunaan::class)
            ->filterTable('activity_group', $master->id)
            ->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_full_request_can_reference_existing_event_and_mixed_assets_keep_separate_statuses(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Uji Lengkap']);
        $user = $this->borrower($unit);
        $event = LoanEvent::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'created_by' => $user->id,
            'name' => 'Simposium Fisika',
        ]);
        $item = $this->item();

        Livewire::actingAs($user)->test(AjukanPeminjaman::class)
            ->fillForm([
                'loan_event_id' => $event->id,
                'name' => 'Simposium Fisika',
                'description' => 'Rangkaian simposium',
                ...$this->window(),
            ])
            ->set('data.needs.'.array_key_first(
                Livewire::actingAs($user)->test(AjukanPeminjaman::class)->get('data.needs')
            ).'.item_id', $item->id)
            ->call('submit')
            ->assertHasNoFormErrors();

        $activity = G004M008Activity::query()->sole();
        $this->assertSame($event->id, $activity->loan_event_id);
        $this->assertSame(1, LoanEvent::query()->count());
    }

    public function test_cross_unit_master_id_is_rejected_even_by_direct_service_call(): void
    {
        $unitA = G001M001Unit::query()->create(['name' => 'Unit A']);
        $unitB = G001M001Unit::query()->create(['name' => 'Unit B']);
        $user = $this->borrower($unitA);
        $other = $this->borrower($unitB);
        $event = LoanEvent::query()->create([
            'g001_m001_unit_id' => $unitB->id,
            'created_by' => $other->id,
            'name' => 'Agenda rahasia',
        ]);
        $this->actingAs($user);
        $this->expectException(ValidationException::class);

        app(LoanRequestService::class)->submit($user, [
            'name' => 'Pengajuan palsu',
            'description' => 'Tidak boleh mengambil kegiatan lintas unit.',
            'loan_event_id' => $event->id,
            ...$this->window(),
            'needs' => [['type' => 'item', 'item_id' => $this->item()->id, 'quantity' => 1]],
        ]);
    }

    public function test_quick_picker_hides_foreign_events_even_if_they_are_submitted_or_cancelled(): void
    {
        $unitA = G001M001Unit::query()->create(['name' => 'Unit Asal']);
        $unitB = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $user = $this->borrower($unitA);
        $other = $this->borrower($unitB);

        $own = LoanEvent::query()->create(['name' => 'Rapat Asal', 'g001_m001_unit_id' => $unitA->id]);
        $external = LoanEvent::query()->create(['name' => 'Rapat Rahasia', 'g001_m001_unit_id' => $unitB->id]);

        $options = app(LoanEventService::class)->options($user);
        $this->assertArrayHasKey($own->id, $options);
        $this->assertArrayNotHasKey($external->id, $options);

        Livewire::actingAs($user)->test(PeminjamanCepat::class)
            ->fillForm([
                'activity_mode' => 'existing',
                'existing_activity_id' => $external->id,
                'asset_id' => $this->item()->id,
                ...$this->window(),
            ])->call('submit')->assertHasErrors();

        $this->assertSame(0, G004M008Activity::query()->count());
        $this->assertSame(2, LoanEvent::query()->count());
    }

    public function test_master_resource_is_scoped_and_existing_requests_still_get_master_links(): void
    {
        $unitA = G001M001Unit::query()->create(['name' => 'Unit Utama']);
        $unitB = G001M001Unit::query()->create(['name' => 'Unit Luar']);
        $user = $this->borrower($unitA);
        $other = $this->borrower($unitB);

        $activity = G004M008Activity::query()->create([
            'user_id' => $user->id,
            'g001_m001_unit_id' => $unitA->id,
            'name' => 'Pengajuan resource lama',
            'status' => ReservationStatus::Returned->value,
        ]);
        $this->assertNotNull($activity->loan_event_id);
        $this->assertSame('Pengajuan resource lama', $activity->loanEvent->name);

        $external = LoanEvent::query()->create([
            'name' => 'Kegiatan Unit Lain',
            'g001_m001_unit_id' => $unitB->id,
        ]);

        $this->actingAs($user)->get(LoanEventResource::getUrl('index'))
            ->assertOk()->assertSee('Pengajuan resource lama')
            ->assertDontSee('Kegiatan Unit Lain');
        $this->actingAs($user)->get(LoanEventResource::getUrl('view', ['record' => $external]))
            ->assertNotFound();
        $this->assertFalse(LoanEventResource::canEdit($activity->loanEvent));
    }
}
