<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\LoanOperationsStats;
use App\Filament\Widgets\LoanStatusChart;
use App\Filament\Widgets\LoanUsageTrendChart;
use App\Filament\Widgets\OperationalPriorityWidget;
use App\Filament\Widgets\RecentLoanRequests;
use App\Models\G001M001Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class RingkasanPeminjaman extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Ringkasan Peminjaman';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Ringkasan Peminjaman';

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Filter Ringkasan')
                ->description('Tanggal dan status memfilter angka pengajuan, grafik, dan daftar terbaru. Unit juga memfilter Prioritas Reservasi Aset; tanggal dan status tidak menyembunyikan pekerjaan aktif atau terlambat. Kalender memiliki rentang sendiri.')
                ->icon('heroicon-o-funnel')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Dari tanggal')
                        ->default(now()->startOfMonth())
                        ->native(false),
                    DatePicker::make('end_date')
                        ->label('Sampai tanggal')
                        ->default(now()->endOfMonth())
                        ->native(false)
                        ->afterOrEqual('start_date'),
                    Select::make('unit_id')
                        ->label('Unit')
                        ->options(fn (): array => G001M001Unit::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (): bool => auth()->user()?->isFacility() ?? false),
                    Select::make('status')
                        ->label('Status')
                        ->options(ReservationStatus::options())
                        ->native(false),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->collapsible(),
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isFacility() || $user->isSarpras() || $user->isAssetManager()));
    }

    public static function getRoutePath(): string
    {
        return '/ringkasan-peminjaman';
    }

    public function getWidgets(): array
    {
        return [
            OperationalPriorityWidget::class,
            LoanOperationsStats::class,
            LoanStatusChart::class,
            LoanUsageTrendChart::class,
            RecentLoanRequests::class,
            CalendarWidget::class,
        ];
    }
}
