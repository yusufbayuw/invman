<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingAndAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatify_uses_application_name_and_logo(): void
    {
        $this->assertSame(config('app.name'), config('chatify.name'));
        $this->assertSame(config('app.logo'), config('chatify.logo'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/chatify')
            ->assertOk()
            ->assertSee('PESAN ' . strtoupper(config('app.name')))
            ->assertSee('Semua Pesan')
            ->assertSee(asset(config('app.logo')), false)
            ->assertDontSee('Chatify Messenger');
    }

    public function test_default_avatar_uses_local_application_logo(): void
    {
        $user = User::factory()->create(['avatar' => 'avatar.png']);

        $this->assertSame(asset(config('app.logo')), $user->getFilamentAvatarUrl());

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
}
