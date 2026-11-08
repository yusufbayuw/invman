<?php

namespace App\Services;

use App\Models\AssetRelation;
use App\Models\G002M015ItemInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRelationService
{
    private const TYPES = ['component', 'contained', 'installed', 'bundle'];

    public function connect(int $parentId, int $childId, string $type, User $actor): AssetRelation
    {
        return DB::transaction(function () use ($parentId, $childId, $type, $actor): AssetRelation {
            if (! in_array($type, self::TYPES, true) || $parentId === $childId) {
                throw ValidationException::withMessages(['relation_type' => 'Relasi aset tidak valid.']);
            }
            $ids = [$parentId, $childId];
            sort($ids);
            $locked = G002M015ItemInstance::query()->whereKey($ids)->orderBy('id')
                ->lockForUpdate()->get()->keyBy('id');
            $parent = $locked->get($parentId);
            $child = $locked->get($childId);
            if (! $parent || ! $child) {
                throw ValidationException::withMessages(['child_instance_id' => 'Unit aset tidak ditemukan.']);
            }
            abort_unless($actor->can('update', $parent) && $actor->can('view', $child), 403);
            if ($parent->g001_m001_unit_id !== $child->g001_m001_unit_id
                || $parent->item?->g002_m003_item_management_id !== $child->item?->g002_m003_item_management_id) {
                throw ValidationException::withMessages(['child_instance_id' => 'Relasi hanya boleh dalam unit dan pengelola barang yang sama.']);
            }
            // Do not alter asset composition while physical custody is outstanding.
            foreach ([$parent, $child] as $unit) {
                if ($unit->item_reservation_detail()->whereHas('item_reservation', fn ($q) => $q->whereIn('status', [
                    'submitted', 'approved', 'checked_out', 'return_requested', 'partially_approved',
                ]))->exists()) {
                    throw ValidationException::withMessages(['child_instance_id' => 'Tidak boleh mengubah susunan saat ada peminjaman aktif.']);
                }
            }
            $edges = AssetRelation::query()->get(['parent_instance_id', 'child_instance_id']);
            $adj = [];
            foreach ($edges as $edge) {
                $adj[$edge->parent_instance_id][] = $edge->child_instance_id;
            }
            // Adding parent -> child is a cycle if child already reaches parent.
            $todo = [$childId];
            $visited = [];
            while ($todo) {
                $id = array_pop($todo);
                if ($id === $parentId) {
                    throw ValidationException::withMessages(['child_instance_id' => 'Relasi akan membuat siklus aset.']);
                }
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                foreach ($adj[$id] ?? [] as $next) {
                    $todo[] = (int) $next;
                }
            }
            if (in_array($type, ['component', 'installed'], true) && $child->is_borrowable) {
                throw ValidationException::withMessages([
                    'child_instance_id' => 'Nonaktifkan Dapat Dipinjam pada komponen/instalasi agar tidak terhitung sebagai aset mandiri.',
                ]);
            }
            if (in_array($type, ['component', 'installed'], true)
                && AssetRelation::query()->where('child_instance_id', $childId)
                    ->whereIn('relation_type', ['component', 'installed'])->exists()) {
                throw ValidationException::withMessages(['child_instance_id' => 'Komponen ini sudah terpasang pada aset lain.']);
            }
            // Kits are inventory structures; automatic combined checkout is not enabled.
            // A kit parent must not be advertised as borrowable until reservation logic covers its members.
            if ($type === 'bundle' && $parent->is_borrowable) {
                throw ValidationException::withMessages(['relation_type' => 'Nonaktifkan Dapat Dipinjam pada induk paket sampai alokasi paket aktif.']);
            }

            return AssetRelation::query()->firstOrCreate([
                'parent_instance_id' => $parentId,
                'child_instance_id' => $childId,
                'relation_type' => $type,
            ]);
        }, 3);
    }

    public function disconnect(AssetRelation $relation, User $actor): void
    {
        DB::transaction(function () use ($relation, $actor): void {
            $snapshot = AssetRelation::query()->findOrFail($relation->id);
            $units = G002M015ItemInstance::query()->whereKey([$snapshot->parent_instance_id, $snapshot->child_instance_id])
                ->orderBy('id')->lockForUpdate()->get();
            $relation = AssetRelation::query()->lockForUpdate()->findOrFail($relation->id);
            abort_unless($actor->can('update', $relation->parentInstance), 403);
            foreach ($units as $unit) {
                if ($unit->item_reservation_detail()->whereHas('item_reservation', fn ($query) => $query->whereIn('status', [
                    'submitted', 'approved', 'checked_out', 'return_requested', 'partially_approved',
                ]))->exists()) {
                    throw ValidationException::withMessages(['relation_type' => 'Tidak boleh mengubah susunan saat ada peminjaman aktif.']);
                }
            }
            $relation->delete();
        });
    }

    public function changeType(AssetRelation $relation, string $type, User $actor): AssetRelation
    {
        return DB::transaction(function () use ($relation, $type, $actor): AssetRelation {
            $parent = (int) $relation->parent_instance_id;
            $child = (int) $relation->child_instance_id;
            $this->disconnect($relation, $actor);

            return $this->connect($parent, $child, $type, $actor);
        }, 3);
    }
}
