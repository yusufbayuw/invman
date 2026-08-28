<?php

namespace App\Filament\Public\Widgets;

use App\Filament\Public\Widgets\Concerns\BuildsPublicLoanScheduleTable;
use App\Models\G005M019VehicleReservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class VehicleLoanSchedule extends TableWidget
{
    use BuildsPublicLoanScheduleTable;

    protected static ?int $sort = -70;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $this->configurePublicTable($table)
            ->heading('Kendaraan')
            ->description('Jadwal peminjaman kendaraan yang aktif atau mendatang.')
            ->query($this->visibleReservations(G005M019VehicleReservation::query())->with(['vehicle', 'activity.unit']))
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.name')->label('Kendaraan')->weight('semibold')->searchable()->placeholder('Kendaraan tidak tercatat'),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label('Nomor Polisi')->badge()->placeholder('Belum tercatat'),
                ...$this->commonColumns(),
            ])
            ->actions([
                $this->detailAction([
                    TextEntry::make('vehicle.name')->label('Kendaraan')->placeholder('Kendaraan tidak tercatat'),
                    TextEntry::make('vehicle.license_plate')->label('Nomor Polisi')->placeholder('Nomor polisi belum tercatat'),
                ]),
            ])
            ->emptyStateHeading('Belum ada jadwal kendaraan')
            ->emptyStateDescription('Tidak ada peminjaman kendaraan yang disetujui atau sedang berjalan.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    protected function assetName(Model $record): string
    {
        return $record->vehicle?->name ?? 'Kendaraan tidak tercatat';
    }
}
