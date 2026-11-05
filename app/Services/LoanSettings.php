<?php

namespace App\Services;

use App\Models\LoanSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanSettings
{
    public const HOLD_ON_SUBMISSION = 'on_submission';
    public const HOLD_ON_APPROVAL = 'on_approval';
    public const CLOCK_ELAPSED = 'elapsed';
    public const CLOCK_WORKING = 'working_hours';

    public function holdHours(): int
    {
        return max(1, min(720, (int) $this->value('hold_hours', config('loans.hold_hours', 24))));
    }

    public function holdStrategy(): string
    {
        $value = $this->value('hold_strategy', self::HOLD_ON_SUBMISSION);

        return in_array($value, [self::HOLD_ON_SUBMISSION, self::HOLD_ON_APPROVAL], true)
            ? $value : self::HOLD_ON_SUBMISSION;
    }

    public function holdClock(): string
    {
        $value = $this->value('hold_clock', self::CLOCK_ELAPSED);

        return in_array($value, [self::CLOCK_ELAPSED, self::CLOCK_WORKING], true)
            ? $value : self::CLOCK_ELAPSED;
    }

    public function workStart(): string
    {
        return $this->value('work_start', '08:00');
    }

    public function workEnd(): string
    {
        return $this->value('work_end', '17:00');
    }

    /** @return list<int> ISO weekday numbers: Monday = 1, Sunday = 7. */
    public function workDays(): array
    {
        $days = json_decode($this->value('work_days', '[1,2,3,4,5]'), true);
        if (! is_array($days) || $days === []) {
            return [1, 2, 3, 4, 5];
        }

        $days = array_values(array_unique(array_map('intval', $days)));

        return count(array_filter($days, fn (int $day): bool => $day >= 1 && $day <= 7)) === count($days)
            ? $days : [1, 2, 3, 4, 5];
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        return [
            'hold_strategy' => $this->holdStrategy(),
            'hold_hours' => $this->holdHours(),
            'hold_clock' => $this->holdClock(),
            'work_start' => $this->workStart(),
            'work_end' => $this->workEnd(),
            'work_days' => array_map('strval', $this->workDays()),
        ];
    }

    public function setHoldHours(int $hours): void
    {
        LoanSetting::query()->updateOrCreate(['key' => 'hold_hours'], ['value' => max(1, min(720, $hours))]);
    }

    /**
     * Changes affect NEW submissions only. Existing activity policy and deadline
     * are snapshotted so administrators cannot silently revoke existing holds.
     *
     * @param array<string, mixed> $values
     */
    public function saveConfiguration(array $values): void
    {
        $values = array_replace($this->configuration(), $values);
        $data = Validator::make($values, [
            'hold_strategy' => ['required', Rule::in([self::HOLD_ON_SUBMISSION, self::HOLD_ON_APPROVAL])],
            'hold_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'hold_clock' => ['required', Rule::in([self::CLOCK_ELAPSED, self::CLOCK_WORKING])],
            'work_start' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            'work_end' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', 'distinct', Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
        ])->validate();

        if ($data['work_end'] <= $data['work_start']) {
            throw ValidationException::withMessages([
                'data.work_end' => 'Jam selesai kerja harus setelah jam mulai kerja.',
            ]);
        }

        DB::transaction(function () use ($data): void {
            foreach (['hold_strategy', 'hold_hours', 'hold_clock', 'work_start', 'work_end', 'work_days'] as $key) {
                $value = $key === 'work_days' ? json_encode(array_map('intval', $data[$key])) : (string) $data[$key];
                LoanSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });
    }

    public function expiresAt(?Carbon $startTime = null): Carbon
    {
        $deadline = $this->holdClock() === self::CLOCK_WORKING
            ? $this->workingDeadline(now(), $this->holdHours())
            : now()->addHours($this->holdHours());

        // The approval deadline may never extend past the usage start.
        return $startTime && $startTime->lessThan($deadline) ? $startTime->copy() : $deadline;
    }

    private function workingDeadline(Carbon $from, int $hours): Carbon
    {
        $remainingMinutes = $hours * 60;
        $cursor = $from->copy();
        $days = $this->workDays();
        $open = $this->workStart();
        $close = $this->workEnd();

        // Up to 720 hours, including one-day work weeks, without infinite loops.
        for ($day = 0; $day < 6000; $day++) {
            if (in_array($cursor->dayOfWeekIso, $days, true)) {
                $opening = $cursor->copy()->setTimeFromTimeString($open);
                $closing = $cursor->copy()->setTimeFromTimeString($close);
                $begin = $cursor->greaterThan($opening) ? $cursor->copy() : $opening;
                if ($begin->lessThan($closing)) {
                    $minutes = (int) $begin->diffInMinutes($closing);
                    if ($remainingMinutes <= $minutes) {
                        return $begin->addMinutes($remainingMinutes);
                    }
                    $remainingMinutes -= $minutes;
                }
            }
            $cursor->addDay()->startOfDay();
        }

        throw new \RuntimeException('Batas persetujuan tidak dapat dihitung dari kalender jam kerja.');
    }

    private function value(string $key, mixed $default): string
    {
        return (string) (LoanSetting::query()->where('key', $key)->value('value') ?? $default);
    }
}
