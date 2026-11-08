<?php

namespace Tests\Feature;

use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Pages\PeminjamanCepat;
use App\Filament\Resources\TicketResource;
use App\Filament\Widgets\DashboardShortcuts;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPracticalActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_facility_and_sarpras_have_three_practical_actions_before_monitoring_links(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        foreach ([config('role.admin'), config('role.fasilitas'), config('role.sarpras')] as $role) {
            $unit = G001M001Unit::create(['name' => 'Unit '.$role]);
            $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
            $user->assignRole($role);
            $this->actingAs($user);
            $links = collect(app(DashboardShortcuts::class)->shortcuts())->where('featured', true)->values();
            $this->assertSame(['Pinjaman Cepat', 'Pinjaman Multi-aset', 'Laporan Kerusakan'], $links->pluck('label')->all());
            $this->assertSame([PeminjamanCepat::getUrl(), AjukanPeminjaman::getUrl(), TicketResource::getUrl('create')], $links->pluck('url')->all());
            $this->get('/admin')->assertOk()->assertSeeInOrder(['Tindakan Cepat', 'Pinjaman Cepat', 'Pinjaman Multi-aset', 'Laporan Kerusakan', 'Pantau &amp; Tindak Lanjut'], false);
        }
    }

    public function test_asset_manager_does_not_gain_loan_creation_from_the_shortcuts(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $group = G002M003ItemManagement::create(['name' => 'Pengelola']);
        $group->users()->attach($user->id);
        $this->actingAs($user);
        $links = collect(app(DashboardShortcuts::class)->shortcuts())->where('featured', true)->pluck('label')->all();
        $this->assertSame(['Laporan Kerusakan'], $links);
        $this->get('/admin/peminjaman-cepat')->assertForbidden();
        $this->get('/admin/ajukan-peminjaman')->assertForbidden();
    }
}
