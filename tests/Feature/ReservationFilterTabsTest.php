<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G005M009ItemReservationResource\Pages\ListG005M009ItemReservations;
use App\Filament\Resources\G005M010RoomReservationResource\Pages\ListG005M010RoomReservations;
use App\Filament\Resources\G005M019VehicleReservationResource\Pages\ListG005M019VehicleReservations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFilterTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_reservation_lists_have_the_same_status_filter_tabs(): void
    {
        $expectedTabs = [
            'all',
            ...array_map(fn (ReservationStatus $status): string => $status->value, ReservationStatus::cases()),
        ];

        foreach ([
            new ListG005M009ItemReservations,
            new ListG005M010RoomReservations,
            new ListG005M019VehicleReservations,
        ] as $page) {
            $this->assertSame($expectedTabs, array_keys($page->getTabs()));
        }
    }
}
