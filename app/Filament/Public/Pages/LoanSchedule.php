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
        if (! auth()->user()?->is_enabled) {
            return 'Jadwal, jumlah, dan status dapat dilihat. Nama aset, kegiatan, unit, lokasi, dan nomor polisi disamarkan sebagian. Masuk untuk melihat detail lengkap.';
        }

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
                ->label(auth()->user()?->is_enabled ? 'Buka Dasbor' : 'Masuk untuk Melihat Data')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->url('/admin'),
        ];
    }
}
