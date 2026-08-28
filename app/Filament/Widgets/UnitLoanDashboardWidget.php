<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Pages\RekapanPenggunaan;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use Filament\Widgets\Widget;

class UnitLoanDashboardWidget extends Widget
{
    protected static string $view = 'filament.widgets.unit-loan-dashboard-widget';
    protected static ?int $sort = -100;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->isSarpras();
    }

    public function getViewData(): array
    {
        $query = G004M008Activity::query()
            ->where('g001_m001_unit_id', auth()->user()->g001_m001_unit_id);

        return [
            'submitUrl' => AjukanPeminjaman::getUrl(),
            'mineUrl' => G004M008ActivityResource::getUrl('index'),
            'reportUrl' => RekapanPenggunaan::getUrl(),
            'counts' => [
                'submitted' => (clone $query)->where('status', ReservationStatus::Submitted->value)->count(),
                'approved' => (clone $query)->whereIn('status', [
                    ReservationStatus::Approved->value,
                    ReservationStatus::PartiallyApproved->value,
                ])->count(),
                'checked_out' => (clone $query)->where('status', ReservationStatus::CheckedOut->value)->count(),
                'returned' => (clone $query)->where('status', ReservationStatus::Returned->value)->count(),
                'expired' => (clone $query)->where('status', ReservationStatus::Expired->value)->count(),
            ],
        ];
    }
}
