<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LoanQuickActionService
{
    public function __construct(private readonly LoanRequestService $loans) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user): Collection
    {
        if (! $user->isFacility() && ! $user->isSarpras() && ! $user->isAssetManager()) {
            return collect();
        }

        $managementIds = $user->isFacility()
            ? collect()
            : $user->itemManagements()->pluck('g002_m003_item_management.id');

        return collect($this->reservationTypes())
            ->flatMap(function (array $configuration, string $type) use ($managementIds, $user): Collection {
                $model = $configuration['model'];

                return $model::query()
                    ->with([
                        'activity.unit',
                        'activity.user',
                        'returnReceipt',
                        $configuration['asset_relation'],
                    ])
                    ->whereIn('status', [
                        ReservationStatus::Submitted->value,
                        ReservationStatus::Approved->value,
                        ReservationStatus::CheckedOut->value,
                        ReservationStatus::ReturnRequested->value,
                    ])
                    ->when(! $user->isFacility(), function (Builder $query) use ($configuration, $managementIds, $user): void {
                        $query->where(function (Builder $scope) use ($configuration, $managementIds, $user): void {
                            $scope->whereRaw('1 = 0');

                            if ($managementIds->isNotEmpty()) {
                                $scope->orWhereHas(
                                    $configuration['asset_relation'],
                                    fn (Builder $asset): Builder => $asset->whereIn('g002_m003_item_management_id', $managementIds),
                                );
                            }

                            if ($user->isSarpras() && filled($user->g001_m001_unit_id)) {
                                $scope->orWhereHas(
                                    'activity',
                                    fn (Builder $activity): Builder => $activity->where('g001_m001_unit_id', $user->g001_m001_unit_id),
                                );
                            }
                        });
                    })
                    ->get()
                    ->map(fn (Model $reservation): ?array => $this->toQueueItem($type, $configuration, $reservation, $user))
                    ->filter();
            })
            ->sortBy([
                ['priority', 'asc'],
                ['sort_at', 'asc'],
                ['key', 'asc'],
            ])
            ->values();
    }

    /** @return array<string, array<string, string>> */
    private function reservationTypes(): array
    {
        return [
            'item' => [
                'model' => G005M009ItemReservation::class,
                'asset_relation' => 'item',
                'label' => 'Barang',
                'icon' => 'heroicon-o-cube',
            ],
            'room' => [
                'model' => G005M010RoomReservation::class,
                'asset_relation' => 'room',
                'label' => 'Ruangan',
                'icon' => 'heroicon-o-building-office',
            ],
            'vehicle' => [
                'model' => G005M019VehicleReservation::class,
                'asset_relation' => 'vehicle',
                'label' => 'Kendaraan',
                'icon' => 'heroicon-o-truck',
            ],
        ];
    }

    /** @param array<string, string> $configuration */
    private function toQueueItem(string $type, array $configuration, Model $reservation, User $user): ?array
    {
        $actions = $this->loans->availableQuickActions($reservation, $user);

        if ($actions === []) {
            return null;
        }

        $status = ReservationStatus::tryFrom($reservation->status);
        $asset = $reservation->getRelation($configuration['asset_relation']);
        $assetName = $asset?->name ?? 'Aset tidak tersedia';

        if ($reservation instanceof G005M009ItemReservation && $reservation->quantity > 1) {
            $assetName .= ' × '.$reservation->quantity;
        }

        if ($reservation instanceof G005M019VehicleReservation && filled($asset?->license_plate)) {
            $assetName .= ' · '.$asset->license_plate;
        }

        $primaryAction = $actions[0];
        $sortAt = match ($primaryAction) {
            'confirmReturn' => $reservation->status_changed_at ?? $reservation->updated_at,
            'approve' => $reservation->activity?->hold_expires_at ?? $reservation->created_at,
            'checkout' => $reservation->start_time,
            default => $reservation->end_time,
        };

        return [
            'key' => $type.':'.$reservation->getKey(),
            'type' => $type,
            'type_label' => $configuration['label'],
            'type_icon' => $configuration['icon'],
            'reservation_id' => (string) $reservation->getKey(),
            'asset_name' => $assetName,
            'activity_name' => $reservation->activity?->name ?? '-',
            'unit_name' => $reservation->activity?->unit?->name ?? '-',
            'requester_name' => $reservation->activity?->user?->name ?? '-',
            'schedule' => $reservation->start_time?->translatedFormat('d M Y, H:i').' – '.$reservation->end_time?->translatedFormat('d M Y, H:i'),
            'status_label' => $reservation->isOverdue() ? 'Terlambat' : ($status?->label() ?? $reservation->status),
            'status_color' => $reservation->isOverdue() ? 'danger' : ($status?->color() ?? 'gray'),
            'actions' => $actions,
            'priority' => match ($primaryAction) {
                'confirmReturn' => 0,
                'approve' => 1,
                'checkout' => 2,
                default => 3,
            },
            'sort_at' => $sortAt?->timestamp ?? PHP_INT_MAX,
        ];
    }
}
