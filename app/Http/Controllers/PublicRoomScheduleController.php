<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\G003M006Room;
use Illuminate\Contracts\View\View;

class PublicRoomScheduleController extends Controller
{
    public function __invoke(G003M006Room $room): View
    {
        $visibleStatuses = ReservationStatus::confirmedBlockingValues();

        $reservations = $room->room_reservation()
            ->with('activity.unit')
            ->whereIn('status', $visibleStatuses)
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->paginate(15);

        $currentReservation = $room->room_reservation()
            ->with('activity.unit')
            ->whereIn('status', $visibleStatuses)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->first();

        return view('public.rooms.show', compact('room', 'reservations', 'currentReservation'));
    }
}
