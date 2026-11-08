<?php

namespace App\Services;

use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G008M017Vehicle;
use App\Models\RecurringAssetSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringAssetScheduleService
{
    public static function assetModels(): array
    {
        return ['item' => G002M007Item::class, 'room' => G003M006Room::class, 'vehicle' => G008M017Vehicle::class];
    }

    public function blockedIds(string $type, array $ids, Carbon $start, Carbon $end): array
    {
        $model = self::assetModels()[$type];

        return RecurringAssetSchedule::query()->where('asset_type', $model)->whereIn('asset_id', $ids)
            ->where('is_active', true)->where('valid_from', '<=', $end->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $start->copy()->subDay()->toDateString()))
            // Current reads are required after waiting for the asset lock under MySQL REPEATABLE READ.
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->get()->filter(fn ($schedule) => $schedule->overlaps($start, $end))
            ->pluck('asset_id')->unique()->values()->all();
    }

    public function isBlocked(string $type, int $id, Carbon $start, Carbon $end): bool
    {
        return $this->blockedIds($type, [$id], $start, $end) !== [];
    }

    public function assertNoExistingBooking(RecurringAssetSchedule $schedule, Model $asset): void
    {
        $relation = match ($asset::class) {
            G002M007Item::class => 'item_reservation',
            G003M006Room::class => 'room_reservation',
            G008M017Vehicle::class => 'vehicle_reservation',
        };
        $query = $asset->$relation()->where(function ($query): void {
            app(LoanAvailabilityService::class)->applyBlockingScope($query, locking: true);
        })->where('end_time', '>', now())->lockForUpdate();
        foreach ($query->get() as $reservation) {
            if ($reservation->start_time && $reservation->end_time && $schedule->overlaps($reservation->start_time, $reservation->end_time)) {
                throw ValidationException::withMessages(['weekdays' => 'Jadwal rutin bertabrakan dengan booking aktif '.$reservation->id.'. Sesuaikan jadwal atau selesaikan perubahan booking terlebih dahulu.']);
            }
        }
    }
}
