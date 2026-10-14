<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TicketTeamMember extends Model
{
    protected $fillable = [
        'user_id', 'supervisor_user_id', 'g002_m003_item_management_id',
        'team_name', 'team_type', 'position', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class, 'supervisor_user_id'); }
    public function management(): BelongsTo
    {
        return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $member): void {
            $member->team_name = trim((string) $member->team_name);
            if ($member->team_name === '' || mb_strlen($member->team_name) > 100
                || ! in_array($member->team_type, ['field', 'support'], true)
                || ! in_array($member->position, ['lead', 'technician'], true)) {
                throw ValidationException::withMessages(['team_name' => 'Nama, jenis tim, atau jabatan tidak valid.']);
            }

            $user = User::query()->find($member->user_id);
            if (! $user || ! $user->is_enabled) {
                throw ValidationException::withMessages(['user_id' => 'Pilih akun petugas yang aktif.']);
            }
            // Prevent editing/deactivating a supervisor in a way that would
            // orphan active descendants. Reassign them first.
            if ($member->exists) {
                $children = self::query()->where('supervisor_user_id', $member->user_id)
                    ->where('is_active', true)->get();
                if ($children->isNotEmpty()) {
                    if (! $member->is_active || $member->position !== 'lead') {
                        throw ValidationException::withMessages([
                            'is_active' => 'Pindahkan bawahan aktif sebelum menonaktifkan atau mengubah jabatan atasan.',
                        ]);
                    }
                    foreach ($children as $child) {
                        if ($child->team_name !== $member->team_name
                            || $child->team_type !== $member->team_type
                            || (int) $child->g002_m003_item_management_id !== (int) $member->g002_m003_item_management_id) {
                            throw ValidationException::withMessages([
                                'team_name' => 'Tim/pengelola atasan tidak boleh berubah selama masih memiliki bawahan aktif.',
                            ]);
                        }
                    }
                }
            }

            if ($member->supervisor_user_id === null) {
                // Only leads are roots of a ticket dispatch tree.
                if ($member->position !== 'lead') {
                    throw ValidationException::withMessages(['supervisor_user_id' => 'Petugas harus memiliki atasan tim.']);
                }
                return;
            }
            if ((int) $member->supervisor_user_id === (int) $member->user_id) {
                throw ValidationException::withMessages(['supervisor_user_id' => 'Tidak dapat menjadi atasan diri sendiri.']);
            }

            $seen = [(int) $member->user_id => true];
            $cursor = (int) $member->supervisor_user_id;
            for ($depth = 0; $depth < 100; $depth++) {
                if (isset($seen[$cursor])) {
                    throw ValidationException::withMessages(['supervisor_user_id' => 'Hierarki tim tidak boleh membentuk siklus.']);
                }
                $seen[$cursor] = true;
                $supervisor = self::query()->where('user_id', $cursor)->first();
                if (! $supervisor || ! $supervisor->is_active
                    || $supervisor->position !== 'lead'
                    || $supervisor->team_name !== $member->team_name
                    || $supervisor->team_type !== $member->team_type
                    || (int) $supervisor->g002_m003_item_management_id !== (int) $member->g002_m003_item_management_id) {
                    throw ValidationException::withMessages(['supervisor_user_id' => 'Atasan harus koordinator aktif dalam tim dan pengelola yang sama.']);
                }
                if (! $supervisor->supervisor_user_id) {
                    return;
                }
                $cursor = (int) $supervisor->supervisor_user_id;
            }
            throw ValidationException::withMessages(['supervisor_user_id' => 'Hierarki tim terlalu dalam.']);
        });

        static::deleting(fn (): never => throw ValidationException::withMessages([
            'team' => 'Anggota tidak boleh dihapus agar histori penugasan tetap terlacak. Nonaktifkan saja.',
        ]));
    }

    /** @return list<int> */
    public static function descendantsOf(User $lead): array
    {
        $root = self::query()->where('user_id', $lead->id)
            ->where('is_active', true)->where('position', 'lead')->first();
        if (! $root) {
            return [];
        }
        $members = self::query()
            ->where('team_name', $root->team_name)
            ->where('team_type', $root->team_type)
            ->where('is_active', true)
            ->where('g002_m003_item_management_id', $root->g002_m003_item_management_id)
            ->with('user:id,is_enabled')
            ->get();
        $children = [];
        foreach ($members as $member) {
            if ($member->user?->is_enabled && $member->supervisor_user_id) {
                $children[(int) $member->supervisor_user_id][] = (int) $member->user_id;
            }
        }
        $seen = [$lead->id => true];
        $frontier = [(int) $lead->id];
        $result = [];
        while ($frontier !== []) {
            $parent = array_shift($frontier);
            foreach ($children[$parent] ?? [] as $child) {
                if (isset($seen[$child])) {
                    continue;
                }
                $seen[$child] = true;
                $result[] = $child;
                $frontier[] = $child;
            }
        }
        return $result;
    }

    public static function qualifiesFor(Ticket $ticket, User $technician): bool
    {
        if (! $technician->is_enabled || (int) $ticket->reporter_id === (int) $technician->id) {
            return false;
        }
        // Existing asset managers and facility roles continue to work without
        // requiring retrospective membership records.
        if ($technician->isFacility()
            || ($ticket->g002_m003_item_management_id
                && $technician->itemManagements()->whereKey($ticket->g002_m003_item_management_id)->exists())) {
            return true;
        }
        $member = self::query()->where('user_id', $technician->id)->where('is_active', true)->first();
        if (! $member) {
            return false;
        }
        // Asset-less reports still belong to a reporting unit. Without this
        // check, a general field team could be dispatched across unrelated
        // units merely because both records had no management identifier.
        if (! $ticket->g002_m003_item_management_id) {
            return ! $ticket->g001_m001_unit_id
                || (int) $technician->g001_m001_unit_id === (int) $ticket->g001_m001_unit_id;
        }

        return (int) $member->g002_m003_item_management_id
            === (int) $ticket->g002_m003_item_management_id;
    }
}
