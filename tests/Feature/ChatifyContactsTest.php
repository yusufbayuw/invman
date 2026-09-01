<?php

namespace Tests\Feature;

use App\Http\Controllers\Chatify\MessagesController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatifyContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_route_uses_the_mysql_compatible_controller(): void
    {
        $route = Route::getRoutes()->getByName('contacts.get');

        $this->assertNotNull($route);
        $this->assertSame(MessagesController::class, $route->getControllerClass());
    }

    public function test_contacts_are_returned_in_latest_conversation_order(): void
    {
        $user = User::factory()->create();
        $olderContact = User::factory()->create(['name' => 'Kontak Lama']);
        $newerContact = User::factory()->create(['name' => 'Kontak Baru']);

        DB::table('ch_messages')->insert([
            [
                'id' => (string) Str::uuid(),
                'from_id' => $user->id,
                'to_id' => $olderContact->id,
                'body' => 'Pesan lama',
                'seen' => false,
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_id' => $newerContact->id,
                'to_id' => $user->id,
                'body' => 'Pesan baru',
                'seen' => false,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/chatify/getContacts')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('last_page', 1);

        $contacts = $response->json('contacts');

        $this->assertStringContainsString($newerContact->name, $contacts);
        $this->assertStringContainsString($olderContact->name, $contacts);
        $this->assertLessThan(
            strpos($contacts, $olderContact->name),
            strpos($contacts, $newerContact->name),
        );
    }
}
