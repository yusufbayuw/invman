<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomChatifyPage;
use App\Filament\Pages\PengaturanAplikasi;
use App\Filament\Widgets\MenuGridWidget;
use App\Models\PinnedMenu;
use App\Models\User;
use App\Services\ApplicationSettings;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_chat_and_reenable_it_without_removing_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_enabled' => true]);
        $admin->assignRole(config('role.admin'));
        $this->actingAs($admin);
        $this->assertTrue(app(ApplicationSettings::class)->chatEnabled());
        $this->assertTrue(CustomChatifyPage::canAccess());

        Livewire::test(PengaturanAplikasi::class)->fillForm(['chat_enabled' => false])->call('save');
        $this->assertFalse(app(ApplicationSettings::class)->chatEnabled());
        $this->assertFalse(CustomChatifyPage::canAccess());
        $this->get('/chatify')->assertForbidden();
        $this->get('/admin/chat')->assertForbidden();
        PinnedMenu::create([
            'user_id' => $admin->id, 'label' => 'Chat',
            'url' => CustomChatifyPage::getUrl(), 'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
        ]);
        Livewire::test(MenuGridWidget::class)->assertSet('pinnedMenus', []);
        $this->get('/admin')->assertOk()->assertDontSee('class="chat-button', false);

        Livewire::test(PengaturanAplikasi::class)->fillForm(['chat_enabled' => true])->call('save');
        $this->assertTrue(CustomChatifyPage::canAccess());
    }

    public function test_regular_user_cannot_change_settings(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $user->assignRole(config('role.sarpras'));
        Livewire::actingAs($user)->test(PengaturanAplikasi::class)->assertForbidden();
        $this->assertTrue(app(ApplicationSettings::class)->chatEnabled());
    }

    public function test_session_preflight_reports_current_identity_and_is_not_cached(): void
    {
        $this->getJson('/session/status')->assertOk()->assertJsonPath('user', '')
            ->assertJsonStructure(['token'])->assertHeader('Cache-Control', 'no-store, private');
        $user = User::factory()->create(['is_enabled' => true]);
        $this->actingAs($user)->getJson('/session/status')->assertOk()
            ->assertJsonPath('user', (string) $user->id)
            ->assertJsonPath('token', session()->token());
    }

    public function test_ajax_session_probe_does_not_replace_previous_page(): void
    {
        $this->get('/login')->assertRedirect('/admin/login');
        $previous = session()->previousUrl();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/session/status')->assertOk();

        $this->assertSame($previous, session()->previousUrl());
    }

    public function test_sidebar_contains_only_five_defined_groups(): void
    {
        $this->assertSame(['Peminjaman', 'Laporan & Perbaikan', 'Laporan', 'Data Aset', 'Pengaturan'],
            array_map(fn ($group) => $group->getLabel(), Filament::getPanel('admin')->getNavigationGroups()));
    }
}
