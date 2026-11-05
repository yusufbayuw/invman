<?php

namespace App\Services;

use App\Models\G004M008Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class LoanVisibility
{
    /**
     * Canonical read boundary shared by Filament, calendar, statistics, search
     * and reports. Query parameters never extend these entitlements.
     */
    public function activities(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isFacility()) {
            return $query;
        }

        $managementIds = $user->itemManagements()
            ->pluck('g002_m003_item_management.id');

        $unitId = $user->isSarpras() ? $user->g001_m001_unit_id : null;

        if (! $unitId && $managementIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visible) use ($unitId, $managementIds): void {
            if ($unitId) {
                $visible->orWhere('g001_m001_unit_id', $unitId);
            }

            if ($managementIds->isNotEmpty()) {
                foreach ([
                    'item_reservation.item',
                    'room_reservation.room',
                    'vehicle_reservation.vehicle',
                ] as $relation) {
                    $visible->orWhereHas($relation, function (Builder $asset) use ($managementIds, $relation): void {
                        $asset->whereIn('g002_m003_item_management_id', $managementIds);
                        if ($relation === 'room_reservation.room') {
                            $asset->whereNotNull('g001_m001_unit_id');
                        }
                    });
                }
            }
        });
    }

    public function reservations(Builder $query, ?User $user, string $relation): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isFacility()) {
            return $query;
        }

        $unitId = $user->isSarpras() ? $user->g001_m001_unit_id : null;
        $managementIds = $user->itemManagements()
            ->pluck('g002_m003_item_management.id');

        if (! $unitId && $managementIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visible) use ($relation, $unitId, $managementIds): void {
            if ($unitId) {
                $visible->orWhereHas('activity', fn (Builder $activity) => $activity
                    ->where('g001_m001_unit_id', $unitId));
            }

            if ($managementIds->isNotEmpty()) {
                $visible->orWhereHas($relation, function (Builder $asset) use ($managementIds, $relation): void {
                    $asset->whereIn('g002_m003_item_management_id', $managementIds);
                    if ($relation === 'room') {
                        $asset->whereNotNull('g001_m001_unit_id');
                    }
                });
            }
        });
    }

    public function canViewActivity(?User $user, G004M008Activity $activity): bool
    {
        return $user !== null
            && $this->activities(G004M008Activity::query(), $user)
                ->whereKey($activity->getKey())
                ->exists();
    }
}
