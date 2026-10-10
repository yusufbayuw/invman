<?php

namespace App\Services;

use App\Models\G004M008Activity;
use App\Models\LoanEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanEventService
{
    public function accessible(User $user): Builder
    {
        return LoanEvent::query()->where('g001_m001_unit_id', $user->g001_m001_unit_id);
    }

    /** @return array<string, string> */
    public function options(User $user): array
    {
        if (! $user->g001_m001_unit_id || ! ($user->isFacility() || $user->isSarpras())) {
            return [];
        }

        return $this->accessible($user)->orderByDesc('created_at')->limit(200)
            ->get()->mapWithKeys(fn (LoanEvent $event): array => [
                $event->id => $event->name.' · '.($event->start_time?->format('d M Y') ?? 'Jadwal fleksibel'),
            ])->all();
    }

    public function resolve(User $user, mixed $id): LoanEvent
    {
        if (! $user->g001_m001_unit_id || ! ($user->isFacility() || $user->isSarpras())
            || ! is_string($id) || ! Str::isUuid($id)
            || ! $event = $this->accessible($user)->whereKey($id)->first()) {
            throw ValidationException::withMessages([
                'data.loan_event_id' => 'Kegiatan tidak tersedia atau bukan milik unit Anda.',
            ]);
        }

        return $event;
    }

    public function createForRequest(User $user, array $data): LoanEvent
    {
        return LoanEvent::query()->create([
            'created_by' => $user->id,
            'g001_m001_unit_id' => $user->g001_m001_unit_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ]);
    }

    /**
     * Accept legacy canonical request IDs for existing deep links and clients.
     * New UI selections always use actual LoanEvent IDs.
     */
    public function resolveSelection(User $user, mixed $id): array
    {
        if (is_string($id) && Str::isUuid($id) && $event = $this->accessible($user)->whereKey($id)->first()) {
            return [$event, null];
        }

        $legacy = app(LoanActivityGrouping::class)->resolve($user, $id);

        if (! $legacy->loan_event_id) {
            throw ValidationException::withMessages([
                'data.existing_activity_id' => 'Kegiatan lama belum dipetakan. Jalankan migrasi database.',
            ]);
        }

        return [$this->resolve($user, $legacy->loan_event_id), $legacy];
    }
}
