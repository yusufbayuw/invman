<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\TugasTeknisi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TechnicianTaskStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return TugasTeknisi::canAccess();
    }

    protected function getStats(): array
    {
        $base = TugasTeknisi::taskQuery();
        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $overdue = (clone $base)->whereIn('status', ['triaged', 'in_progress', 'waiting_parts'])
            ->whereHas('maintenance', fn ($query) => $query
                ->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now()))
            ->count();

        return [
            Stat::make('Perlu Diterima', number_format((int) $counts->get('triaged', 0)))
                ->description('Tugas baru menunggu konfirmasi')->color('warning')
                ->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('Sedang Dikerjakan', number_format((int) $counts->get('in_progress', 0)))
                ->description('Pekerjaan aktif')->color('info')
                ->icon('heroicon-o-wrench-screwdriver'),
            Stat::make('Menunggu Material', number_format((int) $counts->get('waiting_parts', 0)))
                ->description('SLA tetap berjalan')->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Terlambat', number_format($overdue))
                ->description('Batas penyelesaian terlewati')->color($overdue > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
