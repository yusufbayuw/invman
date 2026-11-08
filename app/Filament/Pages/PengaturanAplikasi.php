<?php

namespace App\Filament\Pages;

use App\Services\ApplicationSettings;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PengaturanAplikasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Pengaturan Aplikasi';

    protected static string $view = 'filament.pages.pengaturan-aplikasi';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['chat_enabled' => app(ApplicationSettings::class)->chatEnabled()]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('chat_enabled')->label('Aktifkan menu Chat')
                ->helperText('Menampilkan menu Chat dan tombol chat mengambang untuk pengguna yang memiliki izin. Nonaktifkan untuk menyembunyikan keduanya dan menutup halaman chat. Riwayat pesan tetap tersimpan.'),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        app(ApplicationSettings::class)->setChatEnabled((bool) $this->form->getState()['chat_enabled']);
        Notification::make()->title('Pengaturan aplikasi disimpan')->success()->send();
        $this->redirect(static::getUrl());
    }
}
