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

    protected $attributes = ['is_active' => true];

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
            if ($assignment->is_active && $assignment->supervisor_user_id) {
                $seen = [];
                $frontier = [(int) $assignment->supervisor_user_id];
                for ($depth = 0; $depth < 100; $depth++) {
                    if (in_array((int) $assignment->user_id, $frontier, true)) {
                        throw ValidationException::withMessages(['supervisor_user_id' => 'Hierarki jabatan tidak boleh membentuk siklus.']);
                    }
                    $frontier = array_values(array_diff(array_unique($frontier), $seen));
                    if ($frontier === []) {
                        break;
                    }
                    $seen = array_merge($seen, $frontier);
                    $frontier = self::query()->whereIn('user_id', $frontier)->where('is_active', true)
                        ->where('id', '!=', $assignment->id ?: 0)->whereNotNull('supervisor_user_id')
                        ->pluck('supervisor_user_id')->map(fn ($id): int => (int) $id)->all();
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
