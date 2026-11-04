<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemInstanceAtomicTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function totals(G002M007Item $item, int $quantity, int $available): void
    {
        $item->refresh();
        $this->assertSame([$quantity, $available], [(int) $item->quantity, (int) $item->available_quantity]);
        $this->assertSame($quantity, $item->item_instance()->count());
        $this->assertSame($available, $item->item_instance()->where('is_available', true)->where('is_borrowable', true)->count());
    }

    public function test_inventory_totals_follow_create_flags_transfer_delete_and_transaction_rollback(): void
    {
        $unit = G001M001Unit::create(['name' => 'Atomic inventory QA']);
        $first = G002M007Item::create(['name' => 'First inventory', 'quantity' => 0, 'is_borrowable' => true, 'g001_m001_unit_id' => $unit->id]);
        $second = G002M007Item::create(['name' => 'Second inventory', 'quantity' => 0, 'is_borrowable' => true, 'g001_m001_unit_id' => $unit->id]);
        $instance = G002M015ItemInstance::create(['g002_m007_item_id' => $first->id]);
        $this->totals($first, 1, 1);
        $instance->update(['is_available' => false]);
        $this->totals($first, 1, 0);
        $instance->update(['is_borrowable' => false, 'is_available' => true]);
        $this->totals($first, 1, 0);
        $instance->update(['is_borrowable' => true]);
        $this->totals($first, 1, 1);
        $instance->update(['g002_m007_item_id' => $second->id]);
        $this->totals($first, 0, 0);
        $this->totals($second, 1, 1);
        DB::beginTransaction();
        $instance->update(['is_available' => false]);
        $this->totals($second, 1, 0);
        DB::rollBack();
        $instance->refresh();
        $this->totals($second, 1, 1);
        $instance->delete();
        $this->totals($second, 0, 0);
    }
}
