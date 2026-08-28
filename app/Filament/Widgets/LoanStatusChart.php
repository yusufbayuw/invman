<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Widgets\Concerns\InteractsWithLoanDashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LoanStatusChart extends ChartWidget
{
    use InteractsWithLoanDashboardFilters;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Komposisi Status';

    protected static ?string $description = 'Distribusi seluruh pengajuan pada filter aktif.';

    protected static ?int $sort = -80;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $counts = $this->loanQuery()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statuses = collect(ReservationStatus::cases())
            ->filter(fn (ReservationStatus $status): bool => (int) $counts->get($status->value, 0) > 0);

        return [
            'datasets' => [[
                'label' => 'Pengajuan',
                'data' => $statuses->map(fn (ReservationStatus $status): int => (int) $counts->get($status->value, 0))->values()->all(),
                'backgroundColor' => $statuses->map(fn (ReservationStatus $status): string => match ($status->color()) {
                    'success' => '#10b981',
                    'warning' => '#f59e0b',
                    'danger' => '#f43f5e',
                    'info' => '#3b82f6',
                    'primary' => '#6366f1',
                    default => '#9ca3af',
                })->values()->all(),
            ]],
            'labels' => $statuses->map->label()->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
