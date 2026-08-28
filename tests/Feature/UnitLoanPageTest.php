<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\User;
use App\Filament\Pages\AjukanPeminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitLoanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sarpras_can_open_the_unified_request_page_and_only_see_its_unit_loan_list(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->create(['name' => config('role.unit'), 'guard_name' => 'web']);
        $user->assignRole(config('role.unit'));
        $otherUser = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $otherUser->assignRole(config('role.unit'));
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $outsideUser = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $outsideUser->assignRole(config('role.unit'));
        G004M008Activity::query()->create([
            'user_id' => $user->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Pengajuan milik saya',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'submitted',
        ]);
        G004M008Activity::query()->create([
            'user_id' => $otherUser->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Pengajuan pengguna lain',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'submitted',
        ]);
        G004M008Activity::query()->create([
            'user_id' => $outsideUser->id,
            'g001_m001_unit_id' => $otherUnit->id,
            'name' => 'Pengajuan unit lain',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->get('/admin/ajukan-peminjaman')
            ->assertOk()
            ->assertSee('Ajukan Peminjaman')
            ->assertSee('Ruangan / Tempat');

        $this->actingAs($user)
            ->get('/admin/activity')
            ->assertOk()
            ->assertSee('Peminjaman Saya')
            ->assertSee('Pengajuan milik saya')
            ->assertSee('Pengajuan pengguna lain')
            ->assertDontSee('Pengajuan unit lain');
    }

    public function test_unit_user_can_submit_the_unified_form(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->create(['name' => config('role.unit'), 'guard_name' => 'web']);
        $user->assignRole(config('role.unit'));
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Proyektor',
            'is_borrowable' => true,
            'quantity' => 3,
            'available_quantity' => 3,
        ]);

        $component = Livewire::actingAs($user)
            ->test(AjukanPeminjaman::class)
            ->fillForm([
                'name' => 'Rapat koordinasi',
                'description' => 'Rapat bulanan.',
                'start_time' => now()->addDay()->startOfHour()->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->startOfHour()->addHours(2)->format('Y-m-d H:i:s'),
            ]);

        $needKey = array_key_first($component->get('data.needs'));
        $component
            ->set("data.needs.{$needKey}.type", 'item')
            ->set("data.needs.{$needKey}.quantity", 1)
            ->set("data.needs.{$needKey}.item_id", $item->id);
        $component
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('g004_m008_activities', [
            'user_id' => $user->id,
            'name' => 'Rapat koordinasi',
            'status' => 'submitted',
        ]);
    }

    public function test_request_page_rejects_an_end_time_before_the_start_time(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->create(['name' => config('role.unit'), 'guard_name' => 'web']);
        $user->assignRole(config('role.unit'));

        Livewire::actingAs($user)
            ->test(AjukanPeminjaman::class)
            ->fillForm([
                'name' => 'Jadwal tidak valid',
                'description' => 'Pengujian urutan waktu.',
                'start_time' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->call('submit')
            ->assertHasFormErrors([
                'start_time' => 'before',
                'end_time' => 'after',
            ]);

        $this->assertDatabaseMissing('g004_m008_activities', [
            'name' => 'Jadwal tidak valid',
        ]);
    }
}
