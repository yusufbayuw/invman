<?php
namespace App\Models\Concerns;

use App\Models\{G002M007Item, G002M015ItemInstance, G003M006Room, G008M017Vehicle, TicketAssetLink};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait RetainsTicketAssetHistory
{
    public function delete()
    {
        return DB::transaction(function () {
            if ($this->exists) {
                $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            }
            return parent::delete();
        });
    }

    protected static function bootRetainsTicketAssetHistory(): void
    {
        static::deleting(function (Model $asset): void {
            $type = match (true) {
                $asset instanceof G002M007Item => 'item',
                $asset instanceof G002M015ItemInstance => 'item_instance',
                $asset instanceof G003M006Room => 'room',
                $asset instanceof G008M017Vehicle => 'vehicle',
            };
            $links = TicketAssetLink::query()->where(function ($query) use ($asset, $type): void {
                $query->where(fn ($q) => $q->where('asset_type', $type)->where('asset_id', $asset->getKey()));
                if ($asset instanceof G002M007Item) {
                    $query->orWhere(fn ($q) => $q->where('asset_type', 'item_instance')
                        ->whereIn('asset_id', $asset->item_instance()->select('id')));
                }
            })->lockForUpdate()->get();
            if ($links->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset dengan histori tiket tidak dapat dihapus. Nonaktifkan peminjaman untuk menjaga histori dan safety hold.',
                ]);
            }
        });
    }
}
