<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableAuditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalOverride extends Model
{
    use HasImmutableAuditRecord;

    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'subject_user_id', 'entity_type', 'entity_id', 'action',
        'from_status', 'to_status', 'reason', 'details', 'ip_address', 'user_agent',
    ];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
