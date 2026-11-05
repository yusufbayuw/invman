<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MenuGridWidget;
use Filament\Pages\Page;

class SemuaMenu extends Page
{
    protected static ?string $navigationLabel = 'Semua Menu & Favorit';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.semua-menu';

    public static function canAccess(): bool
    {
        return auth()->check() && MenuGridWidget::canView();
    }

    public function getSubheading(): ?string
    {
        return 'Cari menu yang dapat Anda akses dan simpan favorit. Favorit hanya pintasan, bukan tambahan hak akses.';
    }

    protected function getHeaderWidgets(): array
    {
        return [MenuGridWidget::class];
    }
}
