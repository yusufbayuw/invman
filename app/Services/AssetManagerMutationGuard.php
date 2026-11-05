<?php

namespace App\Services;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AssetManagerMutationGuard
{
    public function item(array $data, User $user): array
    {
        if ($user->isFacility()) {
            return $data;
        }
        $managementId = (int) ($data['g002_m003_item_management_id'] ?? 0);
        abort_unless($user->hasRole(config('role.pengelola_barang'))
            && $user->itemManagements()->whereKey($managementId)->exists(), 403);
        $this->checkRoom($data);
        return $data;
    }

    public function instance(array $data, User $user): array
    {
        if ($user->isFacility()) {
            return $data;
        }
        $item = G002M007Item::query()->findOrFail($data['g002_m007_item_id'] ?? 0);
        abort_unless($user->hasRole(config('role.pengelola_barang'))
            && $user->itemManagements()->whereKey($item->g002_m003_item_management_id)->exists(), 403);
        if ($item->isBulk()) {
            throw ValidationException::withMessages(['g002_m007_item_id' => 'Bulk tidak memiliki Barang Satuan.']);
        }
        // Physical units must follow their catalog owner; never trust forged Livewire unit IDs.
        $data['g001_m001_unit_id'] = $item->g001_m001_unit_id;
        $this->checkRoom($data);
        return $data;
    }

    private function checkRoom(array $data): void
    {
        if (empty($data['g003_m006_room_id'])) {
            return;
        }
        $room = G003M006Room::query()->findOrFail($data['g003_m006_room_id']);
        if ($room->g001_m001_unit_id !== ($data['g001_m001_unit_id'] ?? null)) {
            throw ValidationException::withMessages(['g003_m006_room_id' => 'Ruangan harus berada pada unit pemilik aset.']);
        }
    }
}
