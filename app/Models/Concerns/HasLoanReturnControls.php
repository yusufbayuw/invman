<?php

namespace App\Models\Concerns;

use App\Models\LoanHandoverReceipt;
use App\Models\LoanReservationChecklist;
use App\Models\LoanReservationCorrection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasLoanReturnControls
{
    public function returnChecklists(): HasMany
    {
        return $this->hasMany(LoanReservationChecklist::class, 'reservation_id')
            ->where('reservation_type', $this->loanReservationType());
    }

    public function returnReceipt(): HasOne
    {
        return $this->hasOne(LoanHandoverReceipt::class, 'reservation_id')
            ->where('reservation_type', $this->loanReservationType())
            ->where('direction', 'return')
            ->latestOfMany('created_at');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LoanReservationCorrection::class, 'reservation_id')
            ->where('reservation_type', $this->loanReservationType());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['checked_out', 'return_requested'], true)
            && $this->end_time?->isPast();
    }

    abstract public function loanReservationType(): string;
}
