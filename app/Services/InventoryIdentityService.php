<?php

namespace App\Services;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Support\Facades\DB;

final class InventoryIdentityService
{
    /** Read only. Use the database's actual collation, matching its unique index. */
    public function conflicts(): array
    {
        $result = [];
        foreach (['g002_m007_items', 'g002_m015_item_instances'] as $table) {
            $groups = DB::table($table)->whereNotNull('code')->select('code')
                ->selectRaw('COUNT(*) AS occurrences')->groupBy('code')->havingRaw('COUNT(*) > 1')->get();
            foreach ($groups as $group) {
                $result[] = ['table' => $table, 'code' => $group->code,
                    'ids' => DB::table($table)->where('code', $group->code)->orderBy('id')->pluck('id')->all()];
            }
        }
        return $result;
    }

    /** Caller must save inside a transaction. A stable parent serializes generation. */
    public function nextInstance(G002M007Item $item): array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Inventory code generation requires a transaction.');
        }
        $item = G002M007Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
        $prefix = filled($item->code) ? $item->code.'-' : 'ITEM-'.$item->id.'-';
        $sequence = 1;
        while (G002M015ItemInstance::query()->where('code', $prefix.$sequence)->lockForUpdate()->first()) {
            $sequence++;
        }
        return [$sequence, $prefix.$sequence];
    }
}
