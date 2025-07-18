<?php

namespace App\Observers;

use App\Models\G009M022ItemInstanceChecklist;

class G009M022ItemInstanceChecklistObserver
{
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
    public function updated(G009M022ItemInstanceChecklist $g009M022ItemInstanceChecklist): void
    {
        $g009M022ItemInstanceChecklist->user_id = auth()->user()->id;
        $g009M022ItemInstanceChecklist->checklist_date = now();
        $g009M022ItemInstanceChecklist->saveQuietly();
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
}
