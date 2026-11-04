<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderRevision extends Model
{
    protected $fillable = ['ticket_id', 'rejected_approval_id', 'actor_id', 'revision', 'checkpoint', 'reason', 'before', 'after'];
    protected $casts = ['before' => 'array', 'after' => 'array', 'revision' => 'integer'];

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }

    protected static function booted(): void
    {
        $deny = fn (): never => throw ValidationException::withMessages(['revision' => 'Riwayat revisi tidak dapat diubah atau dihapus.']);
        static::updating($deny);
        static::deleting($deny);
    }
}
