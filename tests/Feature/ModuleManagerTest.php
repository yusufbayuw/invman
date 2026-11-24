<?php

namespace Tests\Feature;

use App\Filament\Pages\ManajemenModul;
use App\Models\User;
use App\Services\ApplicationSettings;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_enabled' => true]);
        $user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));

        return $user;
    }

    public function test_defaults_and_existing_chat_setting(): void
    {
        $manager = app(ModuleManager::class);
        foreach (array_keys($manager->definitions()) as $module) {
            $this->assertTrue($manager->enabled($module));
        }
        app(ApplicationSettings::class)->setChatEnabled(false);
        $this->assertFalse($manager->enabled('chat'));
        $manager->save($this->admin(), ['chat' => true]);
        $this->assertTrue(app(ApplicationSettings::class)->chatEnabled());
    }

    public function test_dependency_preserves_child_preference_and_audits_changes(): void
    {
        $manager = app(ModuleManager::class);
        $actor = $this->admin();
        $manager->save($actor, ['vehicles' => false]);
        $this->assertFalse($manager->loanTypeEnabled('vehicle'));
        $this->assertTrue($manager->requested('loans_vehicle'));
        $this->assertTrue($manager->loanTypeEnabled('room'));
        $manager->save($actor, ['vehicles' => true]);
        $this->assertTrue($manager->loanTypeEnabled('vehicle'));
        $this->assertDatabaseCount('module_setting_audits', 2);
        $manager->save($actor, ['vehicles' => true]);
        $this->assertDatabaseCount('module_setting_audits', 2);
    }

    public function test_disabled_master_and_public_schedule_are_not_accessible_even_to_super_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $manager = app(ModuleManager::class);
        $room = \App\Models\G003M006Room::create(['name' => 'Ruangan yang dirahasiakan']);
        $manager->save($admin, ['inventory' => false, 'rooms' => false, 'public_schedule' => false, 'reports' => false]);

        $this->assertFalse(\App\Filament\Resources\G002M007ItemResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\G003M006RoomResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\G003M006RoomResource::shouldRegisterNavigation());
        $this->assertFalse(\App\Filament\Resources\G003M006RoomResource::canGloballySearch());
        $this->assertFalse(\App\Filament\Pages\RingkasanPeminjaman::canAccess());
        $this->assertFalse(\App\Filament\Public\Widgets\RoomLoanSchedule::canView());
        $this->assertFalse(\App\Filament\Public\Widgets\LoanScheduleStats::canView());
        $this->get($room->publicScheduleUrl())->assertNotFound();
        $this->get($room->qrCodePdfUrl())->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Ruangan yang dirahasiakan');

        $this->assertTrue(ManajemenModul::canAccess());
        $manager->save($admin, ['rooms' => true, 'public_schedule' => true]);
        $this->assertTrue(\App\Filament\Public\Widgets\RoomLoanSchedule::canView());
        $this->get($room->publicScheduleUrl())->assertOk();
    }

    public function test_non_admin_cannot_manage_even_if_authenticated(): void
    {
        $user = User::factory()->create(['is_enabled' => true]);
        $this->actingAs($user);
        $this->assertFalse(ManajemenModul::canAccess());
        try {
            app(ModuleManager::class)->save($user, ['loans' => false]);
            $this->fail('Expected forbidden');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertTrue(app(ModuleManager::class)->enabled('loans'));
        $this->assertSame(0, DB::table('module_setting_audits')->count());
    }
}
