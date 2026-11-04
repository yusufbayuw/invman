<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
