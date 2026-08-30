<?php

namespace App\Observers;

use App\Models\G009M022ItemInstanceChecklist;

class G009M022ItemInstanceChecklistObserver
{
    public function creating(G009M022ItemInstanceChecklist $checklist): void
    {
        if ($this->containsInspection($checklist)) {
            $this->stampInspection($checklist);
        }
    }

    /**
     * Handle the G009M022ItemInstanceChecklist "created" event.
     */
    public function created(G009M022ItemInstanceChecklist $g009M022ItemInstanceChecklist): void
    {
        //
    }

    /**
     * Handle the G009M022ItemInstanceChecklist "updated" event.
     */
    public function updating(G009M022ItemInstanceChecklist $checklist): void
    {
        if (! $checklist->checklist_date && $checklist->isDirty(['is_ok', 'photo', 'notes'])) {
            $this->stampInspection($checklist);
        }
    }

    /**
     * Handle the G009M022ItemInstanceChecklist "deleted" event.
     */
    public function deleted(G009M022ItemInstanceChecklist $g009M022ItemInstanceChecklist): void
    {
        //
    }

    /**
     * Handle the G009M022ItemInstanceChecklist "restored" event.
     */
    public function restored(G009M022ItemInstanceChecklist $g009M022ItemInstanceChecklist): void
    {
        //
    }

    /**
     * Handle the G009M022ItemInstanceChecklist "force deleted" event.
     */
    public function forceDeleted(G009M022ItemInstanceChecklist $g009M022ItemInstanceChecklist): void
    {
        //
    }

    private function containsInspection(G009M022ItemInstanceChecklist $checklist): bool
    {
        return array_key_exists('is_ok', $checklist->getAttributes())
            || filled($checklist->photo)
            || filled($checklist->notes);
    }

    private function stampInspection(G009M022ItemInstanceChecklist $checklist): void
    {
        $checklist->user_id ??= auth()->id();
        $checklist->checklist_date ??= now();
    }
}
