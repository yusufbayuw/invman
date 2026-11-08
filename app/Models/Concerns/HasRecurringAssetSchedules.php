<?php

namespace App\Models\Concerns;

use App\Models\RecurringAssetSchedule;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRecurringAssetSchedules
{
    public function recurringSchedules(): MorphMany
    {
        return $this->morphMany(RecurringAssetSchedule::class, 'asset');
    }
}
