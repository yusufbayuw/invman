<?php

namespace Tests\Feature;

use App\Models\{G001M001Unit, G002M007Item, User};
use App\Services\{LoanAvailabilityService, LoanRequestService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalTimeWindowTest extends TestCase
{
    use RefreshDatabase;

    public static function windows(): array
    {
        return [
            'same_day' => [10, [['2026-11-05 09:00', '2026-11-05 10:00', 3]], '2026-11-05 09:00', '2026-11-05 10:00', 7],
            'cross_midnight' => [10, [['2026-11-05 23:30', '2026-11-06 00:30', 3]], '2026-11-05 23:45', '2026-11-06 00:15', 7],
            'several_days' => [10, [['2026-11-05 09:00', '2026-11-08 12:00', 3]], '2026-11-06 00:00', '2026-11-07 23:00', 7],
            'adjacent_midnight' => [5, [['2026-11-05 23:00', '2026-11-06 00:00', 3], ['2026-11-06 00:00', '2026-11-06 01:00', 3]], '2026-11-05 23:00', '2026-11-06 01:00', 2],
            'adjacent_year_boundary' => [5, [['2026-12-31 23:00', '2027-01-01 00:00', 3], ['2027-01-01 00:00', '2027-01-01 01:00', 3]], '2026-12-31 23:00', '2027-01-01 01:00', 2],
            'future_leap_day' => [5, [['2028-02-28 23:45', '2028-02-29 00:15', 3]], '2028-02-29 00:00', '2028-02-29 00:10', 2],
            'adjacent_minute_boundary' => [5, [['2026-11-05 09:00', '2026-11-05 09:01', 3], ['2026-11-05 09:01', '2026-11-05 09:02', 3]], '2026-11-05 09:00', '2026-11-05 09:02', 2],
            'partial_overlap_peak' => [10, [['2026-11-05 09:00', '2026-11-05 11:00', 3], ['2026-11-05 10:00', '2026-11-05 12:00', 3]], '2026-11-05 09:30', '2026-11-05 11:30', 4],
            'contained_interval_peak' => [10, [['2026-11-05 09:00', '2026-11-05 10:00', 3], ['2026-11-05 09:30', '2026-11-05 09:45', 2]], '2026-11-05 09:00', '2026-11-05 10:00', 5],
            'long_range_separate_reservations' => [5, [['2026-11-05 09:00', '2026-11-05 10:00', 3], ['2026-11-05 11:00', '2026-11-05 12:00', 3]], '2026-11-05 09:00', '2026-11-05 12:00', 2],
            'query_starts_at_previous_end' => [5, [['2026-11-05 09:00', '2026-11-05 10:00', 3]], '2026-11-05 10:00', '2026-11-05 11:00', 5],
            'multi_day_separate_reservations' => [5, [['2026-11-05 09:00', '2026-11-05 10:00', 3], ['2026-11-07 09:00', '2026-11-07 10:00', 3]], '2026-11-05 09:00', '2026-11-07 10:00', 2],
        ];
    }

    #[DataProvider('windows')]
    public function test_displayed_and_locked_stock_agree_at_calendar_boundaries(int $stock, array $bookings, string $start, string $end, int $expected): void
    {
        Carbon::setTestNow('2026-11-04 07:00:00');
        try {
            $unit = G001M001Unit::create(['name' => 'Synthetic calendar QA']);
            $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
            Role::firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
            $user->assignRole(config('role.sarpras'));
            $this->actingAs($user);
            $item = G002M007Item::create(['name' => 'QA calendar stock', 'g001_m001_unit_id' => $unit->id, 'quantity' => $stock, 'available_quantity' => $stock, 'is_borrowable' => true]);
            $requests = app(LoanRequestService::class);
            $data = fn ($from, $to, $quantity) => ['name' => 'Synthetic calendar reservation', 'description' => 'Isolated boundary and peak regression', 'start_time' => $from, 'end_time' => $to, 'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => $quantity]]];
            foreach ($bookings as [$from, $to, $quantity]) {
                $requests->submit($user, $data($from, $to, $quantity));
            }
            $availability = app(LoanAvailabilityService::class);
            $this->assertSame($expected, $availability->availableItemQuantity($item->id, $start, $end));
            $this->assertSame($expected, $availability->availableItemQuantity($item->id, $start, $end, locking: true));
            $this->assertStringContainsString($expected.' tersedia', $availability->itemOptions(Carbon::parse($start), Carbon::parse($end))[$item->id]);
            $requests->submit($user, $data($start, $end, $expected));
            $this->assertSame(0, $availability->availableItemQuantity($item->id, $start, $end));
            try {
                $requests->submit($user, $data($start, $end, 1));
                $this->fail('Submission exceeded the simultaneous stock peak.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.needs.0.quantity', $exception->errors());
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
