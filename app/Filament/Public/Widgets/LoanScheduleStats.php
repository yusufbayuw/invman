<?php

namespace App\Filament\Public\Widgets;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LoanScheduleStats extends StatsOverviewWidget
{
    protected static ?int $sort = -100;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $modules = app(\App\Services\ModuleManager::class);
        return $modules->publicLoanTypeVisible('item') || $modules->publicLoanTypeVisible('room') || $modules->publicLoanTypeVisible('vehicle');
    }

    public function hydrate(): void
    {
        abort_unless(static::canView(), 403);
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan Jadwal';
    }

    protected function getDescription(): ?string
    {
        return 'Jumlah reservasi aktif dan mendatang yang dapat dilihat publik.';
    }

    protected function getStats(): array
    {
        if (! static::canView()) {
            return [];
        }
        $modules = app(\App\Services\ModuleManager::class);
        return array_values(array_filter([
            'item' => Stat::make('Peminjaman Barang', $this->visibleCount(G005M009ItemReservation::query()))
                ->description('Barang dan peralatan')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
            'room' => Stat::make('Peminjaman Ruangan', $this->visibleCount(G005M010RoomReservation::query()))
                ->description('Ruangan dan tempat')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),
            'vehicle' => Stat::make('Peminjaman Kendaraan', $this->visibleCount(G005M019VehicleReservation::query()))
                ->description('Kendaraan operasional')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
        ], fn (string $type): bool => $modules->publicLoanTypeVisible($type), ARRAY_FILTER_USE_KEY));
    }

    private function visibleCount(Builder $query): int
    {
        if (! app(\App\Services\ModuleManager::class)->publicLoanTypeVisible($query->getModel()->loanReservationType())) {
            return 0;
        }
        return $query
            ->whereIn('status', [
                ReservationStatus::Approved->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->where('end_time', '>=', now())
            ->count();
    }
}
