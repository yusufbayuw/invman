<?php

namespace App\Filament\Pages;

use App\Services\ApplicationSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Monzer\FilamentChatifyIntegration\Pages\Chatify;

class CustomChatifyPage extends Chatify
{
    use HasPageShield {
        canAccess as protected shieldCanAccess;
    }

    protected static string $view = 'filament.pages.custom-chatify-page';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static ?string $slug = 'chat';

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $navigationGroup = 'Laporan & Perbaikan';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check()
            && app(ApplicationSettings::class)->chatEnabled()
            && static::shieldCanAccess($parameters);
    }

    public function getTitle(): string
    {
        return 'Chat '.config('app.name');
    }

    /**
     * @return array<string, string>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'chatify-page-active',
        ];
    }
}
