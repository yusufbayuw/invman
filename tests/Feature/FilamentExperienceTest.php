<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G002M007ItemResource;
use App\Filament\Resources\G004M008ActivityResource;
use App\Filament\Widgets\RecentLoanRequests;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FilamentExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_native_operational_widgets(): void
    {
        [$facility] = $this->fixtures();

        $this->actingAs($facility)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dasbor Operasional')
            ->assertSee('Filter Dasbor')
            ->assertSee('Ringkasan Operasional')
            ->assertSee('Pengajuan Terbaru');
    }

    public function test_recent_requests_widget_respects_sarpras_unit_scope(): void
    {
        [, $sarpras, $ownActivity, $otherActivity] = $this->fixtures();

        Livewire::actingAs($sarpras)
            ->test(RecentLoanRequests::class, [
                'filters' => [
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->endOfMonth()->toDateString(),
                ],
            ])
            ->assertCanSeeTableRecords([$ownActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    public function test_global_search_finds_core_inventory_and_activity_records(): void
    {
        [$facility, , $ownActivity, , $item] = $this->fixtures();
        $this->actingAs($facility);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $activityResults = G004M008ActivityResource::getGlobalSearchResults('Koordinasi');
        $itemResults = G002M007ItemResource::getGlobalSearchResults('PRJ-001');

        $this->assertCount(1, $activityResults);
        $this->assertSame($ownActivity->name, (string) $activityResults->first()->title);
        $this->assertCount(1, $itemResults);
        $this->assertSame($item->name, (string) $itemResults->first()->title);
    }

    private function fixtures(): array
    {
        $this->seed(RoleSeeder::class);

        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $facility->givePermissionTo(collect([
            'view_any_g002::m007::item',
            'view_g002::m007::item',
        ])->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ])));
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $otherRequester = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);

        $ownActivity = G004M008Activity::query()->create([
            'user_id' => $sarpras->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Koordinasi Bulanan',
            'description' => 'Kegiatan koordinasi sarpras.',
            'start_time' => now()->startOfMonth()->addDay(),
            'end_time' => now()->startOfMonth()->addDay()->addHours(2),
            'status' => ReservationStatus::Submitted->value,
        ]);
        $otherActivity = G004M008Activity::query()->create([
            'user_id' => $otherRequester->id,
            'g001_m001_unit_id' => $otherUnit->id,
            'name' => 'Kegiatan Unit Lain',
            'start_time' => now()->startOfMonth()->addDays(2),
            'end_time' => now()->startOfMonth()->addDays(2)->addHour(),
            'status' => ReservationStatus::Approved->value,
        ]);
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Proyektor Rapat',
            'code' => 'PRJ-001',
            'quantity' => 3,
            'available_quantity' => 3,
            'is_borrowable' => true,
        ]);

        return [$facility, $sarpras, $ownActivity, $otherActivity, $item];
    }
}
