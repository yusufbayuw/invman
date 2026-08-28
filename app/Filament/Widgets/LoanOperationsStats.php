<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Pages\RekapanPenggunaan;
use App\Filament\Widgets\Concerns\InteractsWithLoanDashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoanOperationsStats extends BaseWidget
{
    use InteractsWithLoanDashboardFilters;
    use InteractsWithPageFilters;

    protected static ?int $sort = -90;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '30s';

    protected function getHeading(): ?string
    {
        return 'Ringkasan Operasional';
    }

    protected function getDescription(): ?string
    {
        return 'Kondisi terkini pengajuan dan pemakaian aset sesuai filter dasbor.';
    }

    protected function getStats(): array
    {
        $query = $this->loanQuery();
        $total = (clone $query)->count();
        $pending = (clone $query)->where('status', ReservationStatus::Submitted->value)->count();
        $approved = (clone $query)->whereIn('status', [
            ReservationStatus::Approved->value,
            ReservationStatus::PartiallyApproved->value,
        ])->count();
        $inUse = (clone $query)->where('status', ReservationStatus::CheckedOut->value)->count();
        $expired = (clone $query)->where('status', ReservationStatus::Expired->value)->count();
        $reportUrl = RekapanPenggunaan::getUrl();

        return [
            Stat::make('Total Pengajuan', number_format($total))
                ->description('Dalam periode terpilih')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart($this->dailyTrend())
                ->url($reportUrl),
            Stat::make('Perlu Persetujuan', number_format($pending))
                ->description($pending > 0 ? 'Membutuhkan tindakan petugas' : 'Antrean sudah bersih')
                ->descriptionIcon($pending > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($pending > 0 ? 'warning' : 'success')
                ->url($reportUrl),
            Stat::make('Disetujui', number_format($approved))
                ->description($total > 0 ? round(($approved / $total) * 100).'% dari pengajuan' : 'Belum ada pengajuan')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($reportUrl),
            Stat::make('Sedang Digunakan', number_format($inUse))
                ->description('Aset sedang berada pada pemohon')
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color('info')
                ->url($reportUrl),
            Stat::make('Hold Kedaluwarsa', number_format($expired))
                ->description('Aset dilepaskan otomatis')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray')
                ->url($reportUrl),
        ];
    }

    private function dailyTrend(): array
    {
        $counts = (clone $this->loanQuery())
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->get(['created_at'])
            ->countBy(fn ($activity): string => $activity->created_at->toDateString());

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): int => $counts->get(now()->subDays($daysAgo)->toDateString(), 0))
            ->all();
    }
}
