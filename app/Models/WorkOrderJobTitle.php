<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkOrderJobTitle extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'jabatan' => 'Jabatan tidak dapat dihapus agar konfigurasi dan histori tetap terlacak. Nonaktifkan saja.',
        ]));
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class, 'work_order_job_title_id');
    }

    public function assignmentWorkflows(): HasMany
    {
        return $this->hasMany(WorkOrderWorkflow::class, 'assign_job_title_id');
    }

    public function closingWorkflows(): HasMany
    {
        return $this->hasMany(WorkOrderWorkflow::class, 'close_job_title_id');
    }

    public function dispatchWorkflows(): HasMany
    {
        return $this->hasMany(WorkOrderWorkflow::class, 'dispatch_job_title_id');
    }
}
