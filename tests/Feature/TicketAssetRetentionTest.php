<?php
namespace Tests\Feature;

use App\Models\{G002M007Item, G002M015ItemInstance, G003M006Room, G008M017Vehicle, Ticket, TicketAssetLink, TicketCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketAssetRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(): Ticket
    {
        $category = TicketCategory::firstOrCreate(['slug' => 'qa-retention'], ['name' => 'QA retention']);
        return Ticket::create(['number' => 'QA-'.bin2hex(random_bytes(5)), 'ticket_category_id' => $category->id,
            'title' => 'Retain historical repair asset', 'description' => 'Synthetic historical asset link', 'status' => 'closed']);
    }

    public function test_assets_and_parent_inventory_with_ticket_history_cannot_be_deleted(): void
    {
        $master = G002M007Item::create(['name' => 'QA retained master', 'quantity' => 1, 'is_borrowable' => true]);
        $instance = $master->item_instance()->firstOrFail();
        $room = G003M006Room::create(['name' => 'QA retained room', 'is_borrowable' => true]);
        $vehicle = G008M017Vehicle::create(['name' => 'QA retained vehicle', 'license_plate' => 'QA-RETAIN', 'is_borrowable' => true]);
        foreach ([['item_instance', $instance], ['room', $room], ['vehicle', $vehicle]] as [$type, $asset]) {
            $ticket = $this->ticket();
            TicketAssetLink::create(['ticket_id' => $ticket->id, 'asset_type' => $type, 'asset_id' => $asset->id]);
            try {
                $asset->delete();
                $this->fail('Repair history asset was deleted');
            } catch (ValidationException) {
                $this->assertNotNull($asset->fresh());
                $this->assertSame(1, $ticket->assets()->count());
            }
        }
        try {
            $master->delete();
            $this->fail('Parent of a retained physical asset was deleted');
        } catch (ValidationException) {
            $this->assertNotNull($master->fresh());
            $this->assertSame(1, (int) $master->fresh()->quantity);
        }
        $direct = G002M007Item::create(['name' => 'QA direct master history', 'quantity' => 0]);
        $ticket = $this->ticket();
        TicketAssetLink::create(['ticket_id' => $ticket->id, 'asset_type' => 'item', 'asset_id' => $direct->id]);
        $this->expectException(ValidationException::class);
        $direct->delete();
    }

    public function test_unreferenced_assets_remain_deletable(): void
    {
        foreach ([G003M006Room::create(['name' => 'Unused room']),
            G008M017Vehicle::create(['name' => 'Unused vehicle', 'license_plate' => 'QA-UNUSED']),
            G002M007Item::create(['name' => 'Unused master', 'quantity' => 0])] as $asset) {
            $this->assertTrue($asset->delete());
            $this->assertNull($asset->fresh());
        }
    }
}
