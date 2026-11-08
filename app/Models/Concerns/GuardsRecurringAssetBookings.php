<?php

namespace App\Models\Concerns;

use App\Enums\ReservationStatus;
use App\Services\RecurringAssetScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GuardsRecurringAssetBookings
{
    /** Keep legacy resource writes under the same asset lock as routine schedules. */
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $type = $this->loanReservationType();
            $key = match ($type) {
                'item' => 'g002_m007_item_id', 'room' => 'g003_m006_room_id', 'vehicle' => 'g008_m017_vehicle_id'
            };
            $status = $this->status ?? ($this->activity?->status === 'draft' ? 'draft' : 'submitted');
            $newBlockingState = $this->isDirty('status') && in_array($status, ['submitted', 'approved'], true);
            if ((! $this->exists || $newBlockingState || $this->isDirty([$key, 'start_time', 'end_time']))
                && in_array($status, ReservationStatus::blockingValues(), true)
                && $this->$key && $this->start_time && $this->end_time) {
                $model = RecurringAssetScheduleService::assetModels()[$type];
                $model::query()->whereKey($this->$key)->lockForUpdate()->firstOrFail();
                if (app(RecurringAssetScheduleService::class)->isBlocked($type, (int) $this->$key, $this->start_time, $this->end_time)) {
                    throw ValidationException::withMessages(['start_time' => 'Jadwal booking bertabrakan dengan jadwal rutin aset. Pilih waktu lain.']);
                }
            }

            return parent::save($options);
        }, 3);
    }
}
