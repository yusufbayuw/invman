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
