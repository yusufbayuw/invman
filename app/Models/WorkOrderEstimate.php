<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class WorkOrderEstimate extends Model
{
    protected $fillable = ['ticket_id', 'revision', 'version', 'execution_mode', 'vendor_name', 'diagnosis', 'materials', 'amount', 'recorded_by'];
    protected $casts = ['materials' => 'array', 'amount' => 'decimal:0', 'revision' => 'integer'];

    protected static function booted(): void
    {
        $deny = fn (): never => throw ValidationException::withMessages(['estimate' => 'Estimasi historis tidak dapat diubah atau dihapus. Catat versi baru.']);
        static::updating($deny);
        static::deleting($deny);
    }
}
