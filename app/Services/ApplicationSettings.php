<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApplicationSettings
{
    public function chatEnabled(): bool
    {
        return DB::table('application_settings')->where('key', 'chat_enabled')->value('value') !== '0';
    }

    public function setChatEnabled(bool $enabled): void
    {
        DB::table('application_settings')->updateOrInsert(['key' => 'chat_enabled'], ['value' => $enabled ? '1' : '0']);
    }
}
