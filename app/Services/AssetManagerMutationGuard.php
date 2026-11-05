<?php

namespace App\Services;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AssetManagerMutationGuard
{
    public function item(array $data, User $user, ?G002M007Item $existing = null): array
    {
        if ($user->isFacility()) {
            return $data;
        }

        $unitId = $data['g001_m001_unit_id'] ?? $existing?->g001_m001_unit_id
            ?? ($user->isSarpras() ? $user->g001_m001_unit_id : null);
        if ($user->isSarpras()) {
            abort_unless($user->g001_m001_unit_id !== null
                && ($existing === null || $user->belongsToUnit($existing->g001_m001_unit_id))
                && (int) $unitId === (int) $user->g001_m001_unit_id, 403);

            $data['g001_m001_unit_id'] = $user->g001_m001_unit_id;
            $this->checkRoom($data, $user->g001_m001_unit_id);

            return $data;
        }

        $managementId = (int) ($data['g002_m003_item_management_id']
            ?? $existing?->g002_m003_item_management_id ?? 0);
        abort_unless($user->hasRole(config('role.pengelola_barang'))
            && $user->itemManagements()->whereKey($managementId)->exists()
            && ($existing === null || $user->itemManagements()
                ->whereKey($existing->g002_m003_item_management_id)->exists()), 403);

        $this->checkRoom($data, $unitId === null ? null : (int) $unitId);

        return $data;
    }

    public function instance(array $data, User $user, ?G002M015ItemInstance $existing = null): array
    {
        $item = G002M007Item::query()->findOrFail(
            $data['g002_m007_item_id'] ?? $existing?->g002_m007_item_id ?? 0,
        );

        if ($item->isBulk()) {
            throw ValidationException::withMessages([
                'g002_m007_item_id' => 'Barang bulk tidak memiliki Barang Satuan.',
            ]);
        }

        if ($user->isSarpras() && ! $user->isFacility()) {
            abort_unless($user->belongsToUnit($item->g001_m001_unit_id)
                && ($existing === null || $user->belongsToUnit(
                    $existing->item?->g001_m001_unit_id,
                )), 403);
        } elseif (! $user->isFacility()) {
            abort_unless($user->hasRole(config('role.pengelola_barang'))
                && $user->itemManagements()->whereKey($item->g002_m003_item_management_id)->exists()
                && ($existing === null || $user->itemManagements()->whereKey(
                    $existing->item?->g002_m003_item_management_id,
                )->exists()), 403);
        }

        if (! $user->isFacility()) {
            // Asset location can change; ownership always follows the parent catalog.
            if (array_key_exists('g001_m001_unit_id', $data)
                && filled($data['g001_m001_unit_id'])
                && (int) $data['g001_m001_unit_id'] !== (int) $item->g001_m001_unit_id) {
                abort(403);
            }
            $data['g001_m001_unit_id'] = $item->g001_m001_unit_id;
            $this->checkRoom($data, $item->g001_m001_unit_id);
        }

        return $data;
    }

    private function checkRoom(array $data, ?int $unitId): void
    {
        if (empty($data['g003_m006_room_id'])) {
            return;
        }

        $room = G003M006Room::query()->findOrFail($data['g003_m006_room_id']);
        // An unassigned room is a physical location, not a separate unit owner.
        if ($room->g001_m001_unit_id !== null
            && (int) $room->g001_m001_unit_id !== (int) $unitId) {
            throw ValidationException::withMessages([
                'g003_m006_room_id' => 'Pilih ruangan unit yang sama atau Ruangan Tanpa Unit.',
            ]);
        }
    }
}
