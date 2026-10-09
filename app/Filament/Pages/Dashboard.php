<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Dasbor';

    protected static ?string $title = 'Dasbor Operasional';

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
            Section::make('Filter Dasbor')
                ->description('Seluruh kartu, grafik, dan daftar terbaru mengikuti filter ini.')
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
                        ->options(fn(): array => G001M001Unit::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn(): bool => auth()->user()?->isFacility() ?? false),
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajukanPeminjaman')
                ->label('Ajukan Peminjaman')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => AjukanPeminjaman::canAccess())
                ->url(AjukanPeminjaman::getUrl()),

            Action::make('peminjamanSaya')
                ->label('Peminjaman Saya')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isSarpras() ?? false)
                ->url(PeminjamanSaya::getUrl()),

            Action::make('rekapan')
                ->label('Lihat Rekapan')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->visible(fn (): bool => RekapanPenggunaan::canAccess())
                ->url(RekapanPenggunaan::getUrl()),
        ];
    }
}
