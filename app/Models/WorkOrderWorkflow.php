<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderWorkflow extends Model
{
    protected $fillable = [
        'g001_m001_unit_id', 'version', 'status', 'steps', 'assign_job_title_id',
        'close_job_title_id', 'dispatch_job_title_id', 'created_by', 'published_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function unit(): BelongsTo { return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id'); }
    public function assignTitle(): BelongsTo { return $this->belongsTo(WorkOrderJobTitle::class, 'assign_job_title_id'); }
    public function closeTitle(): BelongsTo { return $this->belongsTo(WorkOrderJobTitle::class, 'close_job_title_id'); }
    public function dispatchTitle(): BelongsTo { return $this->belongsTo(WorkOrderJobTitle::class, 'dispatch_job_title_id'); }

    protected static function booted(): void
    {
        static::updating(function (self $workflow): void {
            if ($workflow->getOriginal('status') === 'published') {
                throw ValidationException::withMessages(['workflow' => 'Versi terpublikasi tidak dapat diubah. Buat draft baru.']);
            }
        });
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'workflow' => 'Histori aturan tidak boleh dihapus. Buat versi baru jika perlu perubahan.',
        ]));
    }
}
