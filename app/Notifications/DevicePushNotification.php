<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DevicePushNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $type,
        public readonly string $tag,
        public readonly int $ttl = 86400,
    ) {}

    /** @return array<class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(
        object $notifiable,
        Notification $notification
    ): WebPushMessage {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('images/pwa/icon-192.png'))
            ->badge(asset('images/pwa/badge-96.png'))
            ->tag($this->tag)
            ->renotify()
            ->data([
                'url' => $this->url,
                'type' => $this->type,
            ])
            ->options([
                'TTL' => $this->ttl,
                'urgency' => $this->type === 'chat'
                    ? 'high'
                    : 'normal',
            ]);
    }
}
