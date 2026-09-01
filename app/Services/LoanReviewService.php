<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G004M008Activity;
use App\Models\G006M011ItemReview;
use App\Models\G006M012RoomReview;
use App\Models\G006M020VehicleReview;
use App\Models\LoanRequestReview;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoanReviewService
{
    /** @var list<string> */
    private const COMPLETED_STATUSES = [
        ReservationStatus::Returned->value,
        ReservationStatus::Rejected->value,
        ReservationStatus::Cancelled->value,
        ReservationStatus::Expired->value,
    ];

    public function canReview(G004M008Activity $activity, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user?->belongsToUnit($activity->g001_m001_unit_id) || ! $this->hasCompletedLifecycle($activity)) {
            return false;
        }

        $deadline = $this->reviewDeadline($activity);

        return $deadline !== null && now()->lessThanOrEqualTo($deadline);
    }

    public function reviewDeadline(G004M008Activity $activity): ?CarbonInterface
    {
        $returned = $this->reservations($activity)
            ->where('status', ReservationStatus::Returned->value);

        /** @var CarbonInterface|null $lastReturnedAt */
        $lastReturnedAt = $returned
            ->map(fn ($reservation) => $reservation->returned_at ?? $reservation->updated_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();

        return $lastReturnedAt?->copy()->addDays(7);
    }

    /** @return array<string, mixed> */
    public function formData(G004M008Activity $activity): array
    {
        $targets = $this->targets($activity);
        $overall = $activity->review()->first();
        $itemReviews = $activity->item_reviews()->get()->keyBy(
            fn (G006M011ItemReview $review): string => $this->itemKey(
                $review->g005_m009_item_reservation_id,
                $review->g002_m015_item_instance_id,
            ),
        );
        $roomReviews = $activity->room_reviews()->get()->keyBy(
            fn (G006M012RoomReview $review): string => (string) $review->g005_m010_room_reservation_id,
        );
        $vehicleReviews = $activity->vehicle_reviews()->get()->keyBy(
            fn (G006M020VehicleReview $review): string => (string) $review->g005_m019_vehicle_reservation_id,
        );

        return [
            'overall' => [
                'rating' => $overall?->rating,
                'review' => $overall?->review,
            ],
            'items' => collect($targets['items'])->map(function (array $target) use ($itemReviews): array {
                $review = $itemReviews->get($this->itemKey($target['reservation_id'], $target['item_instance_id']));

                return [...$target, 'rating' => $review?->rating, 'review' => $review?->review];
            })->all(),
            'rooms' => collect($targets['rooms'])->map(function (array $target) use ($roomReviews): array {
                $review = $roomReviews->get((string) $target['reservation_id']);

                return [...$target, 'rating' => $review?->rating, 'review' => $review?->review];
            })->all(),
            'vehicles' => collect($targets['vehicles'])->map(function (array $target) use ($vehicleReviews): array {
                $review = $vehicleReviews->get((string) $target['reservation_id']);

                return [...$target, 'rating' => $review?->rating, 'review' => $review?->review];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(G004M008Activity $activity, User $user, array $data): void
    {
        $validated = Validator::make($data, [
            'overall' => ['sometimes', 'array'],
            'overall.rating' => ['nullable', 'integer', 'between:1,5'],
            'overall.review' => ['nullable', 'string', 'max:2000'],
            'items' => ['present', 'array'],
            'items.*.reservation_id' => ['required', 'uuid'],
            'items.*.item_instance_id' => ['required', 'integer'],
            'items.*.rating' => ['nullable', 'integer', 'between:1,5'],
            'items.*.review' => ['nullable', 'string', 'max:2000'],
            'rooms' => ['present', 'array'],
            'rooms.*.reservation_id' => ['required', 'uuid'],
            'rooms.*.rating' => ['nullable', 'integer', 'between:1,5'],
            'rooms.*.review' => ['nullable', 'string', 'max:2000'],
            'vehicles' => ['present', 'array'],
            'vehicles.*.reservation_id' => ['required', 'uuid'],
            'vehicles.*.rating' => ['nullable', 'integer', 'between:1,5'],
            'vehicles.*.review' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($activity, $user, $validated): void {
            $lockedActivity = G004M008Activity::query()->lockForUpdate()->findOrFail($activity->getKey());
            $this->assertCanReview($lockedActivity, $user);

            $targets = $this->targets($lockedActivity);
            $this->assertSubmittedTargetsMatch($targets, $validated);

            $overall = $this->reviewValues($validated['overall'] ?? []);
            if ($this->hasContent($overall)) {
                LoanRequestReview::query()->updateOrCreate(
                    ['g004_m008_activity_id' => $lockedActivity->id],
                    ['user_id' => $user->id, ...$overall],
                );
            } else {
                LoanRequestReview::query()
                    ->where('g004_m008_activity_id', $lockedActivity->id)
                    ->delete();
            }

            $this->syncItemReviews($lockedActivity, $user, $validated['items']);
            $this->syncRoomReviews($lockedActivity, $user, $validated['rooms']);
            $this->syncVehicleReviews($lockedActivity, $user, $validated['vehicles']);
        }, 3);
    }

    private function assertCanReview(G004M008Activity $activity, User $user): void
    {
        if (! $user->belongsToUnit($activity->g001_m001_unit_id)) {
            throw ValidationException::withMessages([
                'review' => 'Hanya anggota unit pemohon yang dapat memberikan ulasan.',
            ]);
        }

        if (! $this->hasCompletedLifecycle($activity)) {
            throw ValidationException::withMessages([
                'review' => 'Ulasan baru dapat diberikan setelah seluruh kebutuhan selesai diproses.',
            ]);
        }

        $deadline = $this->reviewDeadline($activity);
        if ($deadline === null || now()->greaterThan($deadline)) {
            throw ValidationException::withMessages([
                'review' => 'Batas waktu pemberian ulasan selama 7 hari telah berakhir.',
            ]);
        }
    }

    private function hasCompletedLifecycle(G004M008Activity $activity): bool
    {
        $statuses = $this->reservations($activity)->pluck('status')->filter();

        return $statuses->contains(ReservationStatus::Returned->value)
            && $statuses->every(fn (string $status): bool => in_array($status, self::COMPLETED_STATUSES, true));
    }

    /** @return Collection<int, mixed> */
    private function reservations(G004M008Activity $activity): Collection
    {
        return collect()
            ->concat($activity->item_reservation()->get())
            ->concat($activity->room_reservation()->get())
            ->concat($activity->vehicle_reservation()->get());
    }

    /** @return array{items: list<array<string, mixed>>, rooms: list<array<string, mixed>>, vehicles: list<array<string, mixed>>} */
    private function targets(G004M008Activity $activity): array
    {
        $items = $activity->item_reservation()
            ->where('status', ReservationStatus::Returned->value)
            ->with(['item', 'item_reservation_detail.item_instance'])
            ->get()
            ->flatMap(function ($reservation): Collection {
                return $reservation->item_reservation_detail->map(function ($detail) use ($reservation): array {
                    $instance = $detail->item_instance;
                    $name = $instance?->name ?: $reservation->item?->name ?: 'Barang satuan';
                    $code = $instance?->code;

                    return [
                        'reservation_id' => $reservation->id,
                        'item_instance_id' => $detail->g002_m015_item_instance_id,
                        'label' => $code ? "{$name} · {$code}" : $name,
                    ];
                });
            })
            ->values()
            ->all();

        $rooms = $activity->room_reservation()
            ->where('status', ReservationStatus::Returned->value)
            ->with('room')
            ->get()
            ->map(fn ($reservation): array => [
                'reservation_id' => $reservation->id,
                'label' => $reservation->room?->name ?? 'Ruangan',
            ])
            ->values()
            ->all();

        $vehicles = $activity->vehicle_reservation()
            ->where('status', ReservationStatus::Returned->value)
            ->with('vehicle')
            ->get()
            ->map(function ($reservation): array {
                $name = $reservation->vehicle?->name ?? 'Kendaraan';
                $plate = $reservation->vehicle?->license_plate;

                return [
                    'reservation_id' => $reservation->id,
                    'label' => $plate ? "{$name} · {$plate}" : $name,
                ];
            })
            ->values()
            ->all();

        return compact('items', 'rooms', 'vehicles');
    }

    /** @param array<string, mixed> $targets @param array<string, mixed> $data */
    private function assertSubmittedTargetsMatch(array $targets, array $data): void
    {
        $expectedItems = collect($targets['items'])
            ->map(fn (array $target): string => $this->itemKey($target['reservation_id'], $target['item_instance_id']))
            ->sort()->values()->all();
        $submittedItems = collect($data['items'])
            ->map(fn (array $target): string => $this->itemKey($target['reservation_id'], $target['item_instance_id']))
            ->sort()->values()->all();

        $expectedRooms = collect($targets['rooms'])->pluck('reservation_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();
        $submittedRooms = collect($data['rooms'])->pluck('reservation_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();
        $expectedVehicles = collect($targets['vehicles'])->pluck('reservation_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();
        $submittedVehicles = collect($data['vehicles'])->pluck('reservation_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();

        if ($expectedItems !== $submittedItems || $expectedRooms !== $submittedRooms || $expectedVehicles !== $submittedVehicles) {
            throw ValidationException::withMessages([
                'review' => 'Daftar aset berubah atau tidak sesuai dengan pengajuan. Muat ulang halaman dan coba kembali.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $reviews */
    private function syncItemReviews(G004M008Activity $activity, User $user, array $reviews): void
    {
        $keptIds = [];
        foreach ($reviews as $reviewData) {
            $values = $this->reviewValues($reviewData);
            if (! $this->hasContent($values)) {
                continue;
            }

            $review = G006M011ItemReview::query()->updateOrCreate([
                'g004_m008_activity_id' => $activity->id,
                'g005_m009_item_reservation_id' => $reviewData['reservation_id'],
                'g002_m015_item_instance_id' => $reviewData['item_instance_id'],
            ], ['user_id' => $user->id, ...$values]);
            $keptIds[] = $review->id;
        }

        $this->deleteMissing(G006M011ItemReview::class, $activity, $keptIds);
    }

    /** @param list<array<string, mixed>> $reviews */
    private function syncRoomReviews(G004M008Activity $activity, User $user, array $reviews): void
    {
        $reservations = $activity->room_reservation()->whereKey(collect($reviews)->pluck('reservation_id'))->get()->keyBy('id');
        $keptIds = [];
        foreach ($reviews as $reviewData) {
            $values = $this->reviewValues($reviewData);
            if (! $this->hasContent($values)) {
                continue;
            }

            $reservation = $reservations->get($reviewData['reservation_id']);
            $review = G006M012RoomReview::query()->updateOrCreate([
                'g004_m008_activity_id' => $activity->id,
                'g005_m010_room_reservation_id' => $reviewData['reservation_id'],
            ], [
                'g003_m006_room_id' => $reservation->g003_m006_room_id,
                'user_id' => $user->id,
                ...$values,
            ]);
            $keptIds[] = $review->id;
        }

        $this->deleteMissing(G006M012RoomReview::class, $activity, $keptIds);
    }

    /** @param list<array<string, mixed>> $reviews */
    private function syncVehicleReviews(G004M008Activity $activity, User $user, array $reviews): void
    {
        $reservations = $activity->vehicle_reservation()->whereKey(collect($reviews)->pluck('reservation_id'))->get()->keyBy('id');
        $keptIds = [];
        foreach ($reviews as $reviewData) {
            $values = $this->reviewValues($reviewData);
            if (! $this->hasContent($values)) {
                continue;
            }

            $reservation = $reservations->get($reviewData['reservation_id']);
            $review = G006M020VehicleReview::query()->updateOrCreate([
                'g004_m008_activity_id' => $activity->id,
                'g005_m019_vehicle_reservation_id' => $reviewData['reservation_id'],
            ], [
                'g008_m017_vehicle_id' => $reservation->g008_m017_vehicle_id,
                'user_id' => $user->id,
                ...$values,
            ]);
            $keptIds[] = $review->id;
        }

        $this->deleteMissing(G006M020VehicleReview::class, $activity, $keptIds);
    }

    /** @param class-string<G006M011ItemReview|G006M012RoomReview|G006M020VehicleReview> $modelClass @param list<int> $keptIds */
    private function deleteMissing(string $modelClass, G004M008Activity $activity, array $keptIds): void
    {
        $query = $modelClass::query()->where('g004_m008_activity_id', $activity->id);
        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }
        $query->delete();
    }

    /** @param array<string, mixed> $data @return array{rating: int|null, review: string|null} */
    private function reviewValues(array $data): array
    {
        $review = trim((string) ($data['review'] ?? ''));

        return [
            'rating' => filled($data['rating'] ?? null) ? (int) $data['rating'] : null,
            'review' => $review !== '' ? $review : null,
        ];
    }

    /** @param array{rating: int|null, review: string|null} $values */
    private function hasContent(array $values): bool
    {
        return $values['rating'] !== null || $values['review'] !== null;
    }

    private function itemKey(mixed $reservationId, mixed $itemInstanceId): string
    {
        return "{$reservationId}:{$itemInstanceId}";
    }
}
