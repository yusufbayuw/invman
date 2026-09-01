<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DevicePushNotification;
use App\Services\ChatifyMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChatPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chatify.pusher.key', 'test-key');
        config()->set('chatify.pusher.secret', 'test-secret');
        config()->set('chatify.pusher.app_id', 'test-app');
    }

    public function test_new_chat_message_queues_a_sanitized_push_for_the_recipient_only(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['name' => 'Pengirim']);
        $recipient = User::factory()->create();
        $recipient->updatePushSubscription(
            'https://push.example.test/subscriptions/chat-recipient',
            'public-key',
            'auth-token',
            'aes128gcm',
        );

        $encodedBody = htmlentities(
            '<strong>Pesan aman</strong> '.str_repeat('teks panjang ', 20),
            ENT_QUOTES,
            'UTF-8',
        );

        app(ChatifyMessenger::class)->newMessage([
            'from_id' => $sender->getKey(),
            'to_id' => $recipient->getKey(),
            'body' => $encodedBody,
            'attachment' => null,
        ]);

        Notification::assertSentTo(
            $recipient,
            DevicePushNotification::class,
            function (DevicePushNotification $notification) use ($sender): bool {
                return $notification->title === 'Pesan baru dari Pengirim'
                    && str_contains($notification->body, 'Pesan aman')
                    && ! str_contains($notification->body, '<strong>')
                    && mb_strlen($notification->body) <= 120
                    && str_contains($notification->url, "/admin/chat?contact={$sender->getKey()}")
                    && $notification->type === 'chat';
            },
        );
        Notification::assertNotSentTo($sender, DevicePushNotification::class);
    }

    public function test_attachment_only_message_uses_a_generic_preview(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $recipient->updatePushSubscription(
            'https://push.example.test/subscriptions/attachment-recipient',
            'public-key',
            'auth-token',
            'aes128gcm',
        );

        app(ChatifyMessenger::class)->newMessage([
            'from_id' => $sender->getKey(),
            'to_id' => $recipient->getKey(),
            'body' => '',
            'attachment' => json_encode([
                'new_name' => 'secret-server-file.pdf',
                'old_name' => 'dokumen-rahasia.pdf',
            ]),
        ]);

        Notification::assertSentTo(
            $recipient,
            DevicePushNotification::class,
            fn (DevicePushNotification $notification): bool => $notification->body === 'Mengirim sebuah lampiran.'
                && ! str_contains($notification->body, 'dokumen-rahasia'),
        );
    }
}
