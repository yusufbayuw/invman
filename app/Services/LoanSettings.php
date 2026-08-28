<?php

namespace App\Services;

use App\Models\LoanSetting;

class LoanSettings
{
    public function holdHours(): int
    {
        return max(1, (int) (LoanSetting::query()->where('key', 'hold_hours')->value('value') ?? config('loans.hold_hours')));
    }

    public function setHoldHours(int $hours): void
    {
        LoanSetting::query()->updateOrCreate(['key' => 'hold_hours'], ['value' => max(1, $hours)]);
    }
}
