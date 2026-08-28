<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\G004M008Activity;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithLoanDashboardFilters
{
    protected function loanQuery(): Builder
    {
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;
        $status = $this->filters['status'] ?? null;
        $unitId = auth()->user()?->isSarpras()
            ? auth()->user()->g001_m001_unit_id
            : ($this->filters['unit_id'] ?? null);

        return G004M008Activity::query()
            ->when($unitId, fn (Builder $query, $unitId): Builder => $query->where('g001_m001_unit_id', $unitId))
            ->when($status, fn (Builder $query, $status): Builder => $query->where('status', $status))
            ->when($startDate, fn (Builder $query, $date): Builder => $query->whereDate('end_time', '>=', $date))
            ->when($endDate, fn (Builder $query, $date): Builder => $query->whereDate('start_time', '<=', $date));
    }
}
