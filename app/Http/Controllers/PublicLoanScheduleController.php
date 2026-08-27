<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicLoanScheduleController extends Controller
{
    public function __invoke(): View
    {
        $now = now();
        $statuses = [
            ReservationStatus::Approved->value,
            ReservationStatus::CheckedOut->value,
        ];

        $visible = fn (Builder $query): Builder => $query
            ->whereIn('status', $statuses)
            ->where('end_time', '>=', $now)
            ->orderBy('start_time');

        $itemReservations = $visible(G005M009ItemReservation::query())
            ->with(['item', 'activity.unit'])
            ->get();

        $roomReservations = $visible(G005M010RoomReservation::query())
            ->with(['room.floor.building', 'activity.unit'])
            ->get();

        $vehicleReservations = $visible(G005M019VehicleReservation::query())
            ->with(['vehicle', 'activity.unit'])
            ->get();

        return view('public-loan-schedule', compact(
            'itemReservations',
            'roomReservations',
            'vehicleReservations',
            'now',
        ));
    }
}
