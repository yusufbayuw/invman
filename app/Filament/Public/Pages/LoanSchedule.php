<?php

namespace App\Filament\Public\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard;

class LoanSchedule extends Dashboard
{
    protected static ?string $title = 'Jadwal Peminjaman';

    protected static bool $shouldRegisterNavigation = false;

    public function getSubheading(): ?string
    {
        return 'Informasi peminjaman barang, ruangan, dan kendaraan yang sedang berjalan atau telah disetujui.';
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 3,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('login')
                ->label('Masuk Aplikasi')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->url('/admin'),
        ];
    }
}
