<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Pages\PeminjamanCepat;
use App\Filament\Pages\PeminjamanSaya;
use App\Filament\Pages\RingkasanPeminjaman;
use App\Filament\Pages\TugasTeknisi;
use App\Filament\Resources\G004M008ActivityResource;
use App\Filament\Resources\TicketResource;
use Filament\Widgets\Widget;

class DashboardShortcuts extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-shortcuts';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function shortcuts(): array
    {
        $user = auth()->user();
        $links = [];
        if (PeminjamanCepat::canAccess()) {
            $links[] = $this->link('Pinjaman Cepat', 'Satu jenis aset, formulir ringkas.', 'heroicon-o-bolt', PeminjamanCepat::getUrl(), true);
        }
        if (AjukanPeminjaman::canAccess()) {
            $links[] = $this->link('Pinjaman Multi-aset', 'Gabungkan barang, ruangan, dan kendaraan dalam satu pengajuan.', 'heroicon-o-squares-plus', AjukanPeminjaman::getUrl(), true);
        }
        if (TicketResource::canCreate()) {
            $links[] = $this->link('Laporan Kerusakan', 'Laporkan kerusakan dan pantau penanganannya.', 'heroicon-o-wrench-screwdriver', TicketResource::getUrl('create'), true);
        }
        if ($user?->isFacility() && G004M008ActivityResource::canViewAny()) {
            $links[] = $this->link('Tinjau Pengajuan', 'Periksa kebutuhan dan persetujuan.', 'heroicon-o-inbox-stack', G004M008ActivityResource::getUrl());
        }
        if (PeminjamanSaya::canAccess()) {
            $links[] = $this->link(PeminjamanSaya::getNavigationLabel(), 'Lihat status dan tindak lanjut pinjaman.', 'heroicon-o-clipboard-document-list', PeminjamanSaya::getUrl());
        }
        if (TugasTeknisi::hasActiveTasks()) {
            $links[] = $this->link('Tugas Saya', 'Dokumentasikan pekerjaan dan kirim hasil.', 'heroicon-o-wrench-screwdriver', TugasTeknisi::getUrl());
        }
        if (RingkasanPeminjaman::canAccess()) {
            $links[] = $this->link('Lihat Jadwal', 'Cek jadwal peminjaman aset.', 'heroicon-o-calendar-days', url('/'));
        }
        if (! TicketResource::canCreate() && TicketResource::canViewAny()) {
            $links[] = $this->link('Buka Tiket', 'Lihat percakapan dan riwayat penanganan.', 'heroicon-o-ticket', TicketResource::getUrl());
        }

        return $links;
    }

    private function link(string $label, string $description, string $icon, string $url, bool $featured = false): array
    {
        return compact('label', 'description', 'icon', 'url', 'featured');
    }
}
