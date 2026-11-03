<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderAssignment extends Model
{
    protected $fillable = [
        'user_id', 'work_order_job_title_id', 'g001_m001_unit_id',
        'g002_m003_item_management_id', 'supervisor_user_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function jobTitle(): BelongsTo { return $this->belongsTo(WorkOrderJobTitle::class, 'work_order_job_title_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id'); }
    public function management(): BelongsTo { return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class, 'supervisor_user_id'); }

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            if ($assignment->supervisor_user_id && (int) $assignment->supervisor_user_id === (int) $assignment->user_id) {
                throw ValidationException::withMessages(['supervisor_user_id' => 'Pengguna tidak boleh menjadi atasan dirinya sendiri.']);
            }
            if ($assignment->is_active && ! User::query()->whereKey($assignment->user_id)->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'Penugasan aktif membutuhkan pegawai yang aktif.']);
            }
            if ($assignment->supervisor_user_id && ! User::query()->whereKey($assignment->supervisor_user_id)->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['supervisor_user_id' => 'Atasan harus merupakan pegawai aktif.']);
            }
            if ($assignment->supervisor_user_id) {
                $seen = [(int) $assignment->user_id => true];
                $cursor = (int) $assignment->supervisor_user_id;
                for ($depth = 0; $depth < 100; $depth++) {
                    if (isset($seen[$cursor])) {
                        throw ValidationException::withMessages(['supervisor_user_id' => 'Hierarki jabatan tidak boleh membentuk siklus.']);
                    }
                    $seen[$cursor] = true;
                    $next = self::query()->where('user_id', $cursor)->where('is_active', true)
                        ->where('id', '!=', $assignment->id ?: 0)->orderBy('id')
                        ->value('supervisor_user_id');
                    if (! $next) {
                        break;
                    }
                    $cursor = (int) $next;
                }
                if ($depth === 100) {
                    throw ValidationException::withMessages(['supervisor_user_id' => 'Hierarki terlalu dalam.']);
                }
            }
        });
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'penugasan' => 'Histori penugasan tidak dapat dihapus. Nonaktifkan saja.',
        ]));
    }
}
