<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Widgets\Concerns\InteractsWithLoanDashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class LoanUsageTrendChart extends ChartWidget
{
    use InteractsWithLoanDashboardFilters;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Tren Penggunaan';

    protected static ?string $description = 'Jumlah jadwal penggunaan dan pengembalian per hari.';

    protected static ?int $sort = -79;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $end = Carbon::parse($this->filters['end_date'] ?? now())->endOfDay();
        $start = Carbon::parse($this->filters['start_date'] ?? $end->copy()->subDays(13))->startOfDay();

        if ($start->diffInDays($end) > 30) {
            $start = $end->copy()->subDays(30)->startOfDay();
        }

        $records = $this->loanQuery()
            ->whereBetween('start_time', [$start, $end])
            ->get(['start_time', 'status']);
        $dates = collect();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }

        $scheduled = $records->countBy(fn ($activity): string => $activity->start_time->toDateString());
        $completed = $records
            ->where('status', ReservationStatus::Returned->value)
            ->countBy(fn ($activity): string => $activity->start_time->toDateString());

        return [
            'datasets' => [
                [
                    'label' => 'Dijadwalkan',
                    'data' => $dates->map(fn (Carbon $date): int => $scheduled->get($date->toDateString(), 0))->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Selesai',
                    'data' => $dates->map(fn (Carbon $date): int => $completed->get($date->toDateString(), 0))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $dates->map(fn (Carbon $date): string => $date->translatedFormat('d M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
