<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\DevicePushNotification;
use Chatify\ChatifyMessenger as BaseChatifyMessenger;
use Illuminate\Support\Str;

class ChatifyMessenger extends BaseChatifyMessenger
{
    public function newMessage($data)
    {
        $message = parent::newMessage($data);

        if ((string) $message->from_id === (string) $message->to_id) {
            return $message;
        }

        $recipient = User::query()->find($message->to_id);
        $sender = User::query()->find($message->from_id);

        if (! $recipient || ! $sender || ! $recipient->pushSubscriptions()->exists()) {
            return $message;
        }

        $decodedBody = html_entity_decode((string) $message->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $preview = Str::squish(strip_tags($decodedBody));

        if ($preview === '') {
            $preview = $message->attachment
                ? 'Mengirim sebuah lampiran.'
                : 'Mengirim sebuah pesan.';
        }

        $recipient->notify(new DevicePushNotification(
            title: "Pesan baru dari {$sender->name}",
            body: Str::limit($preview, 119, '…'),
            url: url("/admin/chat?contact={$sender->getKey()}"),
            type: 'chat',
            tag: "chat-{$message->getKey()}",
            ttl: 3600,
        ));

        return $message;
    }

    public function getUserWithAvatar($user)
    {
        $avatar = $user->avatar;

        if ($avatar === config('chatify.user_avatar.default') && config('chatify.gravatar.enabled')) {
            $imageSize = config('chatify.gravatar.image_size');
            $imageset = config('chatify.gravatar.imageset');
            $user->avatar = 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($user->email))).'?s='.$imageSize.'&d='.$imageset;

            return $user;
        }

        $user->avatar = $this->getUserAvatarUrl($avatar);

        return $user;
    }

    public function getUserAvatarUrl($userAvatarName)
    {
        $defaultAvatar = config('chatify.user_avatar.default');

        if (blank($userAvatarName) || $userAvatarName === $defaultAvatar) {
            return asset(config('app.logo'));
        }

        if (filter_var($userAvatarName, FILTER_VALIDATE_URL)) {
            return basename((string) parse_url($userAvatarName, PHP_URL_PATH)) === $defaultAvatar
                ? asset(config('app.logo'))
                : $userAvatarName;
        }

        if (Str::startsWith($userAvatarName, ['/storage/', 'storage/'])) {
            return asset(Str::start($userAvatarName, '/'));
        }

        $folder = trim(config('chatify.user_avatar.folder'), '/');
        $path = Str::startsWith($userAvatarName, $folder.'/')
            ? $userAvatarName
            : $folder.'/'.$userAvatarName;

        return self::storage()->url($path);
    }
}
