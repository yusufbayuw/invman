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

    $pending = (clone $query)
        ->where('status', ReservationStatus::Submitted->value)
        ->count();

    $approved = (clone $query)
        ->whereIn('status', [
            ReservationStatus::Approved->value,
            ReservationStatus::PartiallyApproved->value,
        ])
        ->count();

    $inUse = (clone $query)
        ->where('status', ReservationStatus::CheckedOut->value)
        ->count();

    $returned = (clone $query)
        ->where('status', ReservationStatus::Returned->value)
        ->count();

    $expired = (clone $query)
        ->where('status', ReservationStatus::Expired->value)
        ->count();

    return [
        Stat::make('Menunggu', number_format($pending))
            ->description('Menunggu persetujuan')
            ->descriptionIcon('heroicon-m-clock')
            ->color('warning'),

        Stat::make('Disetujui', number_format($approved))
            ->description('Siap digunakan')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success'),

        Stat::make('Sedang Dipakai', number_format($inUse))
            ->description('Sedang digunakan pemohon')
            ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
            ->color('info'),

        Stat::make('Selesai', number_format($returned))
            ->description('Telah dikembalikan')
            ->descriptionIcon('heroicon-m-check-badge')
            ->color('primary'),

        Stat::make('Kedaluwarsa', number_format($expired))
            ->description('Reservasi telah kedaluwarsa')
            ->descriptionIcon('heroicon-m-clock')
            ->color('gray'),
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
