<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\RekapanPenggunaan;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsageReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_can_view_and_filter_the_usage_report(): void
    {
        [$facility, , $ownActivity, $otherActivity] = $this->fixtures();

        $this->actingAs($facility)
            ->get('/admin/rekapan-penggunaan')
            ->assertOk()
            ->assertSee('Rekapan Penggunaan')
            ->assertSee('Detail Penggunaan')
            ->assertSee('Ekspor Rekapan');

        $component = Livewire::actingAs($facility)
            ->test(RekapanPenggunaan::class)
            ->assertCanSeeTableRecords([$ownActivity, $otherActivity]);

        $this->assertSame('2', $component->instance()->getUsageStats()[0]['value']);

        $component
            ->filterTable('status', [ReservationStatus::Returned->value])
            ->assertCanSeeTableRecords([$ownActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);

        $this->assertSame('1', $component->instance()->getUsageStats()[0]['value']);

        $component
            ->removeTableFilters()
            ->filterTable('jenis_kebutuhan', ['jenis' => 'item'])
            ->assertCanSeeTableRecords([$ownActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    public function test_sarpras_report_is_scoped_to_its_own_unit(): void
    {
        [, $sarpras, $ownActivity, $otherActivity] = $this->fixtures();

        Livewire::actingAs($sarpras)
            ->test(RekapanPenggunaan::class)
            ->assertCanSeeTableRecords([$ownActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    public function test_user_without_an_authorized_role_cannot_open_the_report(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/rekapan-penggunaan')
            ->assertForbidden();
    }

    private function fixtures(): array
    {
        $this->seed(RoleSeeder::class);

        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $otherRequester = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);

        $ownActivity = G004M008Activity::query()->create([
            'user_id' => $sarpras->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan selesai',
            'description' => 'Memakai barang inventaris.',
            'start_time' => now()->subDays(3),
            'end_time' => now()->subDays(3)->addHours(2),
            'status' => ReservationStatus::Returned->value,
        ]);

        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Proyektor',
            'quantity' => 2,
            'available_quantity' => 2,
            'is_borrowable' => true,
        ]);

        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $ownActivity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $ownActivity->start_time,
            'end_time' => $ownActivity->end_time,
            'status' => ReservationStatus::Returned->value,
        ]);

        $otherActivity = G004M008Activity::query()->create([
            'user_id' => $otherRequester->id,
            'g001_m001_unit_id' => $otherUnit->id,
            'name' => 'Kegiatan menunggu',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);

        return [$facility, $sarpras, $ownActivity, $otherActivity];
    }
}
