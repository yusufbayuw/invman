<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\DashboardShortcuts;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\TechnicianTaskStats;
use App\Filament\Widgets\TicketAttentionWidget;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dasbor';

    protected static ?string $title = 'Dasbor Operasional';

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return match (true) {
            $user?->isAdmin() => 'Pantau pekerjaan operasional dan kelola sistem dari satu tempat.',
            $user?->isFacility() => 'Tinjau persetujuan, penyerahan, pengembalian, dan laporan yang perlu ditangani.',
            $user?->isSarpras() => 'Ajukan dan tindak lanjuti kebutuhan unit Anda.',
            $user?->isAssetManager() => 'Tindak lanjuti peminjaman dan kerusakan aset yang Anda kelola.',
            default => 'Lihat tugas Anda, dokumentasikan pekerjaan, dan kirim hasil perbaikan.',
        };
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 4];
    }

    public function getWidgets(): array
    {
        $widgets = [DashboardShortcuts::class];

        if (QuickActionsWidget::canView()) {
            $widgets[] = QuickActionsWidget::class;
        }

        if (TicketAttentionWidget::canView()) {
            $widgets[] = TicketAttentionWidget::class;
        }

        if (TugasTeknisi::hasActiveTasks()) {
            $widgets[] = TechnicianTaskStats::class;
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('ajukanLainnya')->label('Ajukan Peminjaman')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (): bool => PeminjamanCepat::canAccess() && (auth()->user()?->isFacility() ?? false))
                    ->url(PeminjamanCepat::getUrl()),
                Action::make('tugasSaya')->label('Tugas Saya')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (): bool => TugasTeknisi::hasActiveTasks() && (auth()->user()?->isFacility() ?? false))
                    ->url(TugasTeknisi::getUrl()),
                Action::make('pengguna')->label('Kelola Pengguna')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false) && UserResource::canViewAny())
                    ->url(UserResource::getUrl()),
                Action::make('ringkasan')->label('Ringkasan Peminjaman')
                    ->icon('heroicon-o-chart-bar')->visible(fn (): bool => RingkasanPeminjaman::canAccess())
                    ->url(RingkasanPeminjaman::getUrl()),
                Action::make('semuaMenu')->label('Semua Menu & Favorit')
                    ->icon('heroicon-o-squares-2x2')->visible(fn (): bool => SemuaMenu::canAccess())
                    ->url(SemuaMenu::getUrl()),
            ])->label('Akses Lainnya')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),
        ];
    }
}
