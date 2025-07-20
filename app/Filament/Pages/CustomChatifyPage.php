<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Monzer\FilamentChatifyIntegration\Pages\Chatify;

class CustomChatifyPage extends Chatify
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';
    protected static ?string $slug = "chat";
    protected static ?string $navigationLabel = "Chat";
    protected static ?string $navigationGroup = "Koordinasi";
    protected static ?string $title = "Chat";
}
