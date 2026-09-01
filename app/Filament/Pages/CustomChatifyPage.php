<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Monzer\FilamentChatifyIntegration\Pages\Chatify;

class CustomChatifyPage extends Chatify
{
    use HasPageShield;
    protected static string $view = 'filament.pages.custom-chatify-page';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';
    protected static ?string $slug = "chatify";
    protected static ?string $navigationLabel = "Chat";
    protected static ?string $navigationGroup = "Koordinasi";

    public function getTitle(): string
    {
        return 'Chat ' . config('app.name');
    }
}
