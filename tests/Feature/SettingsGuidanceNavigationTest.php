<?php

namespace Tests\Feature;

use App\Filament\Pages\PengaturanPeminjaman;
use App\Filament\Pages\SemuaMenu;
use App\Filament\Resources\G001M001UnitResource;
use App\Filament\Resources\TicketCategoryResource;
use App\Filament\Resources\TicketTeamMemberResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WorkOrderAssignmentResource;
use App\Filament\Resources\WorkOrderJobTitleResource;
use App\Filament\Resources\WorkOrderWorkflowResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsGuidanceNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['is_enabled' => true]);
        $user->assignRole(config('role.admin'));

        return $user;
    }

    public function test_configuration_navigation_is_kept_in_one_group_and_sorted_in_setup_order(): void
    {
        $this->actingAs($this->administrator());

        $expected = [
            'Unit', 'Peran', 'Pengguna', 'Jabatan Work Order',
            'Penugasan Jabatan', 'Tim & Hierarki Petugas',
            'Kategori Laporan', 'Alur Persetujuan Work Order',
            'Pengaturan Peminjaman', 'Semua Menu & Favorit',
        ];

        $this->assertSame('Pengaturan', G001M001UnitResource::getNavigationGroup());
        $this->assertSame('Pengaturan', PengaturanPeminjaman::getNavigationGroup());
        $this->assertSame('Pengaturan', SemuaMenu::getNavigationGroup());
        $this->assertSame(2, config('filament-shield.shield_resource.navigation_sort'));

        foreach ([
            G001M001UnitResource::class => 1,
            UserResource::class => 3,
            WorkOrderJobTitleResource::class => 4,
            WorkOrderAssignmentResource::class => 5,
            TicketTeamMemberResource::class => 6,
            TicketCategoryResource::class => 7,
            WorkOrderWorkflowResource::class => 8,
            PengaturanPeminjaman::class => 9,
            SemuaMenu::class => 100,
        ] as $class => $sort) {
            $this->assertSame('Pengaturan', $class::getNavigationGroup());
            $this->assertSame($sort, $class::getNavigationSort());
        }

        $navigation = collect(Filament::getNavigation())
            ->first(fn ($group): bool => $group->getLabel() === 'Pengaturan');

        $this->assertNotNull($navigation);
        $labels = collect($navigation->getItems())->map(fn ($item): string => $item->getLabel())->values()->all();

        foreach ($expected as $label) {
            $this->assertContains($label, $labels);
        }

        $this->assertSame($expected, array_values(array_filter(
            $labels, fn (string $label): bool => in_array($label, $expected, true),
        )));
    }

    public function test_shield_permission_advice_is_displayed_without_replacing_its_resource(): void
    {
        $this->actingAs($this->administrator())
            ->get('/admin/shield/roles')
            ->assertOk()
            ->assertSee('Panduan pengaturan Peran')
            ->assertSee('super-admin')
            ->assertSee('Permission / izin');
    }

    public function test_hold_guidance_responds_to_selected_working_hours_without_saving(): void
    {
        Livewire::actingAs($this->administrator())
            ->test(PengaturanPeminjaman::class)
            ->set('data.hold_hours', 24)
            ->set('data.hold_clock', 'working_hours')
            ->set('data.work_days', ['1', '2', '3', '4', '5'])
            ->set('data.work_start', '07:00')
            ->set('data.work_end', '15:00')
            ->assertSee('24 jam KERJA')
            ->assertSee('Senin, Selasa, Rabu, Kamis, Jumat')
            ->assertSee('07:00');
    }
}
