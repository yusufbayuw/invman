<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomChatifyPage;
use App\Models\User;
use Chatify\Facades\ChatifyMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BrandingAndAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logo_is_centered(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('class="mx-auto block w-52"', false);
    }

    public function test_chatify_uses_application_name_and_logo(): void
    {
        $this->assertSame(config('app.name'), config('chatify.name'));
        $this->assertSame(config('app.logo'), config('chatify.logo'));

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::query()->create([
            'name' => config('filament-shield.permission_prefixes.page').'_'.class_basename(CustomChatifyPage::class),
            'guard_name' => 'web',
        ]));

        $this->actingAs($user)
            ->get(CustomChatifyPage::getUrl())
            ->assertOk()
            ->assertSee('chatify-page-active', false)
            ->assertSee('class="chatify-page-shell"', false)
            ->assertSee('id="message-form"', false)
            ->assertSee('PESAN '.strtoupper(config('app.name')))
            ->assertSee('Semua Pesan')
            ->assertSee(asset(config('app.logo')), false)
            ->assertDontSee('Chatify Messenger');
    }

    public function test_default_avatar_uses_fav_image(): void
    {
        $user = User::factory()->create(['avatar' => 'avatar.png']);

        $this->assertSame(asset('images/app/fav.png'), $user->getFilamentAvatarUrl());

        $this->get('/storage/users-avatar/avatar.png')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_uploaded_avatar_uses_chatify_public_storage_url(): void
    {
        $user = User::factory()->create(['avatar' => 'foto-pengguna.jpg']);

        $this->assertStringEndsWith(
            '/storage/users-avatar/foto-pengguna.jpg',
            $user->getFilamentAvatarUrl(),
        );
    }

    public function test_filament_does_not_prefix_an_avatar_url_formatted_by_chatify(): void
    {
        $user = User::factory()->create(['avatar' => 'foto-pengguna.jpg']);
        $avatarUrl = asset('storage/users-avatar/foto-pengguna.jpg');

        $user->avatar = $avatarUrl;

        $this->assertSame($avatarUrl, $user->getFilamentAvatarUrl());
    }

    public function test_chatify_formatted_default_avatar_still_uses_fav_image(): void
    {
        $user = User::factory()->create(['avatar' => 'avatar.png']);
        $user->avatar = asset('storage/users-avatar/avatar.png');

        $this->assertSame(asset('images/app/fav.png'), $user->getFilamentAvatarUrl());
    }

    public function test_chatify_uses_application_logo_for_default_avatar(): void
    {
        $user = User::factory()->create(['avatar' => 'avatar.png']);

        $this->assertSame(
            asset(config('app.logo')),
            ChatifyMessenger::getUserWithAvatar($user)->avatar,
        );
    }

    public function test_chatify_does_not_duplicate_the_avatar_folder(): void
    {
        $user = User::factory()->create(['avatar' => 'users-avatar/foto-pengguna.jpg']);

        $this->assertStringEndsWith(
            '/storage/users-avatar/foto-pengguna.jpg',
            ChatifyMessenger::getUserWithAvatar($user)->avatar,
        );
    }
}
