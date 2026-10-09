<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Filament\Resources\G005M009ItemReservationResource;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\Concerns\InteractsWithLoanDashboardFilters;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\User;
use App\Services\LoanVisibility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanAccessAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sarpras_and_manager_visibility_is_consistent_across_resources_dashboard_calendar_and_policy(): void
    {
        [$unitA, $unitB, $sarprasA, $sarprasB, $facility, $manager, $outsider, $managed] = $this->setupActors();
        $this->actingAs($facility);

        $activityA = $this->activity($unitA, $sarprasA, 'SD');
        $activityB = $this->activity($unitB, $sarprasB, 'SMP managed');
        $activityC = $this->activity($unitB, $sarprasB, 'SMP private');

        $item = G002M007Item::query()->create([
            'name' => 'Proyektor Unit B',
            'g001_m001_unit_id' => $unitB->id,
            'g002_m003_item_management_id' => $managed->id,
            'is_borrowable' => true,
            'quantity' => 1,
        ]);
        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activityB->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $activityB->start_time,
            'end_time' => $activityB->end_time,
            'status' => ReservationStatus::Submitted->value,
        ]);

        // One activity can combine assets managed by different teams.
        // An asset manager may see the activity context, NOT the other team's need.
        $outsideItem = G002M007Item::query()->create([
            'name' => 'Kamera bukan tanggung jawab IT',
            'g001_m001_unit_id' => $unitB->id,
            'is_borrowable' => true,
            'quantity' => 1,
        ]);
        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activityB->id,
            'g002_m007_item_id' => $outsideItem->id,
            'quantity' => 1,
            'start_time' => $activityB->start_time,
            'end_time' => $activityB->end_time,
            'status' => ReservationStatus::Submitted->value,
        ]);

        $this->actingAs($manager);
        $managerNeeds = G005M009ItemReservationResource::getEloquentQuery()->pluck('g002_m007_item_id')->all();
        $this->assertEqualsCanonicalizing([$item->id], $managerNeeds);

        $visibility = app(LoanVisibility::class);
        foreach ([
            [$sarprasA, [$activityA->id]],
            [$sarprasB, [$activityB->id, $activityC->id]],
            [$facility, [$activityA->id, $activityB->id, $activityC->id]],
            [$manager, [$activityB->id]],
            [$outsider, []],
        ] as [$user, $expected]) {
            $this->actingAs($user);
            $this->assertEqualsCanonicalizing($expected, $visibility->activities(G004M008Activity::query(), $user)->pluck('id')->all());
            $this->assertEqualsCanonicalizing($expected, G004M008ActivityResource::getEloquentQuery()->pluck('id')->all());
            $this->assertEqualsCanonicalizing($expected, G004M008ActivityResource::getGlobalSearchEloquentQuery()->pluck('g004_m008_activities.id')->all());

            $dashboard = new class {
                use InteractsWithLoanDashboardFilters;
                public array $filters = ['unit_id' => null];
                public function ids(): array
                {
                    return $this->loanQuery()->pluck('id')->all();
                }
            };
            $this->assertEqualsCanonicalizing($expected, $dashboard->ids());
            $events = (new CalendarWidget)->fetchEvents([
                'start' => now()->subDay()->toDateTimeString(),
                'end' => now()->addDays(3)->toDateTimeString(),
            ]);
            $this->assertEqualsCanonicalizing($expected, array_column($events, 'id'));

            foreach ([$activityA, $activityB, $activityC] as $activity) {
                $this->assertSame(in_array($activity->id, $expected, true), $user->can('view', $activity));
            }
        }

        // A crafted client-side unit filter must not grant cross-unit visibility.
        $this->actingAs($sarprasA);
        $dashboard = new class {
            use InteractsWithLoanDashboardFilters;
            public array $filters = [];
            public function ids(): array { return $this->loanQuery()->pluck('id')->all(); }
        };
        $dashboard->filters = ['unit_id' => $unitB->id];
        $this->assertEqualsCanonicalizing([$activityA->id], $dashboard->ids());
    }

    public function test_database_rejects_deleting_actors_units_or_assets_that_have_loan_history(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite tidak mendukung migrasi DROP FK; dijalankan oleh MySQL CI.');
        }

        [$unitA, , $sarprasA, , $facility] = $this->setupActors();
        $this->actingAs($facility);
        $activity = $this->activity($unitA, $sarprasA, 'Audit tetap tersimpan');
        $item = G002M007Item::query()->create([
            'name' => 'Kamera Arsip',
            'g001_m001_unit_id' => $unitA->id,
            'is_borrowable' => true,
            'quantity' => 1,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value,
        ]);

        foreach ([
            fn () => DB::table('users')->where('id', $sarprasA->id)->delete(),
            fn () => DB::table('g001_m001_units')->where('id', $unitA->id)->delete(),
            fn () => DB::table('g002_m007_items')->where('id', $item->id)->delete(),
            fn () => DB::table('g004_m008_activities')->where('id', $activity->id)->delete(),
        ] as $destructiveDelete) {
            try {
                $destructiveDelete();
                $this->fail('Expected RESTRICT foreign key to preserve referenced history.');
            } catch (QueryException $exception) {
                // Database rejects deletion even when Eloquent observers are bypassed.
            }
            $this->assertDatabaseHas('g004_m008_activities', ['id' => $activity->id]);
            $this->assertDatabaseHas('g005_m009_item_reservations', ['id' => $reservation->id]);
        }
    }

    private function setupActors(): array
    {
        foreach (['fasilitas', 'sarpras'] as $name) {
            Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $unitA = G001M001Unit::query()->create(['name' => 'SD test']);
        $unitB = G001M001Unit::query()->create(['name' => 'SMP test']);
        $sarprasA = User::factory()->create(['g001_m001_unit_id' => $unitA->id]);
        $sarprasA->assignRole('sarpras');
        $sarprasB = User::factory()->create(['g001_m001_unit_id' => $unitB->id]);
        $sarprasB->assignRole('sarpras');
        $facility = User::factory()->create();
        $facility->assignRole('fasilitas');
        $manager = User::factory()->create();
        $outsider = User::factory()->create();
        $managed = G002M003ItemManagement::query()->create(['name' => 'Pengelola IT']);
        $managed->users()->attach($manager);

        return [$unitA, $unitB, $sarprasA, $sarprasB, $facility, $manager, $outsider, $managed];
    }

    private function activity(G001M001Unit $unit, User $requester, string $name): G004M008Activity
    {
        return G004M008Activity::query()->create([
            'user_id' => $requester->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => $name,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
    }
}
