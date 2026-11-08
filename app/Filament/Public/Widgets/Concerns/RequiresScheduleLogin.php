<?php

namespace App\Filament\Public\Widgets\Concerns;

trait RequiresScheduleLogin
{
    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_enabled;
    }

    // Visibility alone is not authorization: guard direct mounts and subsequent
    // Livewire requests, including a previously valid snapshot after logout.
    public function bootRequiresScheduleLogin(): void
    {
        abort_unless(static::canView(), 403);
    }
}
