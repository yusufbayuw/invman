<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G004M008Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Groups related loan requests under an existing activity without sharing
 * any loan status, availability lock, or approval state.
 */
class LoanActivityGrouping
{
    public function eligibleRoots(User $user): Builder
    {
        return G004M008Activity::query()
            ->where('g001_m001_unit_id', $user->g001_m001_unit_id)
            ->whereNull('related_activity_id')
            ->whereNotIn('status', [
                ReservationStatus::Draft->value,
                ReservationStatus::Cancelled->value,
                ReservationStatus::Expired->value,
                ReservationStatus::Rejected->value,
            ]);
    }

    /** @return array<string, string> */
    public function options(User $user): array
    {
        if (! $user->g001_m001_unit_id || ! ($user->isSarpras() || $user->isFacility())) {
            return [];
        }

        return $this->eligibleRoots($user)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'name', 'start_time'])
            ->mapWithKeys(fn (G004M008Activity $activity): array => [
                $activity->id => $activity->name.' · '.($activity->start_time?->format('d M Y') ?? 'Tanpa jadwal'),
            ])
            ->all();
    }

    public function resolve(User $user, mixed $rootId): G004M008Activity
    {
        if (! $user->g001_m001_unit_id || ! ($user->isSarpras() || $user->isFacility())
            || ! is_string($rootId) || ! \Illuminate\Support\Str::isUuid($rootId)) {
            throw ValidationException::withMessages([
                'data.existing_activity_id' => 'Pilih kegiatan yang valid dari unit Anda.',
            ]);
        }

        $root = $this->eligibleRoots($user)->whereKey($rootId)->first();

        if (! $root) {
            throw ValidationException::withMessages([
                'data.existing_activity_id' => 'Kegiatan tidak tersedia atau bukan milik unit Anda.',
            ]);
        }

        return $root;
    }
}
