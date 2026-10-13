<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TicketAssetSafetyService
{
    private function holds(): Builder
    {
        return DB::table('ticket_maintenance_cases as m')
            ->join('tickets as t', 't.id', '=', 'm.ticket_id')
            ->join('ticket_asset_links as a', 'a.ticket_id', '=', 't.id')
            ->whereNotNull('m.asset_hold_at')
            ->whereNull('m.asset_released_at')
            ->whereNotIn('t.status', ['cancelled']);
    }

    /**
     * Read active safety holds for an entire page of loan-picker options.
     * De-duplicate asset links: different tickets can hold the same asset.
     *
     * @param list<int|string> $ids
     * @return list<int>
     */
    public function blockedAssetIds(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->holds()
            ->where('a.asset_type', $type)
            ->whereIn('a.asset_id', $ids)
            ->distinct()
            ->pluck('a.asset_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Count borrowable instances blocked by distinct active maintenance cases
     * across many item masters. Mirrors blockedItemQuantity() semantics.
     *
     * @param list<int|string> $itemIds
     * @return array<int,int>
     */
    public function blockedBorrowableItemQuantities(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return $this->holds()
            ->join('g002_m015_item_instances as i', function ($join): void {
                $join->on('i.id', '=', 'a.asset_id')
                    ->where('a.asset_type', '=', 'item_instance');
            })
            ->whereIn('i.g002_m007_item_id', $itemIds)
            ->where('i.is_borrowable', true)
            ->select('i.g002_m007_item_id')
            ->selectRaw('COUNT(DISTINCT i.id) as blocked_quantity')
            ->groupBy('i.g002_m007_item_id')
            ->pluck('blocked_quantity', 'i.g002_m007_item_id')
            ->mapWithKeys(fn ($qty, $id): array => [(int) $id => (int) $qty])
            ->all();
    }

    public function isBlocked(string $type, int $id): bool
    {
        return $this->holds()->where('a.asset_type', $type)->where('a.asset_id', $id)->exists();
    }

    /** Distinct IDs prevent double subtraction for assets mentioned by multiple tickets. */
    public function blockedItemInstanceIds(int $itemId): array
    {
        return $this->holds()
            ->join('g002_m015_item_instances as i', function ($join): void {
                $join->on('i.id', '=', 'a.asset_id')
                    ->where('a.asset_type', '=', 'item_instance');
            })
            ->where('i.g002_m007_item_id', $itemId)
            ->distinct()->pluck('i.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function blockedItemQuantity(int $itemId): int
    {
        return DB::table('g002_m015_item_instances')
            ->whereIn('id', $this->blockedItemInstanceIds($itemId))
            ->where('is_borrowable', true)
            ->count();
    }
}
