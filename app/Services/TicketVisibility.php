<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketVisibility
{
    public function query(?User $user): Builder
    {
        $query = Ticket::query();

        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if ($user->isFacility()) {
            return $query;
        }

        $managementIds = $user->itemManagements()->pluck('g002_m003_item_management.id');

        return $query->where(function (Builder $visible) use ($user, $managementIds): void {
            $visible->where('reporter_id', $user->id)
                ->orWhere('assigned_to', $user->id);

            if ($managementIds->isNotEmpty()) {
                $visible->orWhereIn('g002_m003_item_management_id', $managementIds);
            }
        });
    }

    public function canView(?User $user, Ticket $ticket): bool
    {
        return $user !== null && $this->query($user)->whereKey($ticket->id)->exists();
    }

    public function canManage(?User $user, Ticket $ticket): bool
    {
        if (! $user) {
            return false;
        }
        return $user->isFacility()
            || $ticket->assigned_to === $user->id
            || (filled($ticket->g002_m003_item_management_id)
                && $user->itemManagements()->whereKey($ticket->g002_m003_item_management_id)->exists());
    }
}
