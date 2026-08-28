<?php

namespace App\Models;

use App\Observers\G009M022ItemInstanceChecklistObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(G009M022ItemInstanceChecklistObserver::class)]
class G009M022ItemInstanceChecklist extends Model
{
    protected $casts = [
        'date' => 'date',
        'checklist_date' => 'datetime',
        'is_ok' => 'boolean',
    ];

    public function item_instance(): BelongsTo
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'g002_m015_item_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
