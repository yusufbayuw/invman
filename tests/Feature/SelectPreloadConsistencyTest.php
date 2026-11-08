<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Models\G001M001Unit;
use App\Models\G003M006Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class SelectPreloadConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_dynamic_action_and_filter_select_defaults_are_searchable_and_preloaded(): void
    {
        $select = Select::make('type')->options(['a' => 'A', 'b' => 'B']);
        $this->assertTrue($select->isSearchable());
        $this->assertTrue($select->isPreloaded());
        $this->assertFalse($select->isNative());
        $this->assertSame(['a' => 'A', 'b' => 'B'], $select->getOptions());
        $host = new class extends Component implements HasForms
        {
            use InteractsWithForms;
        };
        $dynamic = Select::make('dynamic')->getSearchResultsUsing(fn (string $search) => ['1' => 'Pilihan awal']);
        Form::make($host)->schema([$dynamic])->getComponents();
        $this->assertSame([1 => 'Pilihan awal'], $dynamic->getOptions());
        $filter = SelectFilter::make('status')->options(['active' => 'Aktif']);
        $this->assertTrue($filter->getSearchable());
        $this->assertTrue($filter->isPreloaded());
        $this->assertFalse($filter->isNative());
    }

    public function test_async_asset_select_preloads_real_data_without_disclosing_other_units(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $unit = G001M001Unit::create(['name' => 'Unit saya']);
        $other = G001M001Unit::create(['name' => 'Unit lain']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $user->assignRole(config('role.sarpras'));
        $ownRoom = G003M006Room::create(['name' => 'Ruangan saya', 'g001_m001_unit_id' => $unit->id]);
        $otherRoom = G003M006Room::create(['name' => 'Ruangan lain', 'g001_m001_unit_id' => $other->id]);
        $page = Livewire::actingAs($user)->test(CreateTicket::class)->set('data.link_asset', true)->set('data.asset_type', 'room');
        $asset = collect($page->instance()->form->getFlatComponents())->first(fn ($field) => $field instanceof Select && $field->getName() === 'asset_id');
        $this->assertNotNull($asset);
        $this->assertTrue($asset->isSearchable());
        $this->assertTrue($asset->isPreloaded());
        $this->assertArrayHasKey($ownRoom->id, $asset->getOptions());
        $this->assertArrayNotHasKey($otherRoom->id, $asset->getOptions());
    }
}
