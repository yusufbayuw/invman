<?php

namespace App\Filament\Public\Widgets;

use App\Filament\Public\Widgets\Concerns\BuildsPublicLoanScheduleTable;
use App\Models\G005M009ItemReservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class ItemLoanSchedule extends TableWidget
{
    use BuildsPublicLoanScheduleTable;

    protected static ?int $sort = -90;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $this->configurePublicTable($table)
            ->heading('Barang & Peralatan')
            ->description('Jadwal peminjaman barang yang aktif atau mendatang.')
            ->query($this->visibleReservations(G005M009ItemReservation::query())->with(['item', 'activity.unit']))
            ->columns([
                Tables\Columns\TextColumn::make('item.name')->label('Barang')->weight('semibold')->searchable()->placeholder('Barang tidak tercatat'),
                Tables\Columns\TextColumn::make('quantity')->label('Jumlah')->suffix(' unit'),
                ...$this->commonColumns(),
            ])
            ->actions([
                $this->detailAction([
                    TextEntry::make('item.name')->label('Barang')->placeholder('Barang tidak tercatat'),
                    TextEntry::make('quantity')->label('Jumlah')->suffix(' unit'),
                ]),
            ])
            ->emptyStateHeading('Belum ada jadwal barang')
            ->emptyStateDescription('Tidak ada peminjaman barang yang disetujui atau sedang berjalan.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    protected function assetName(Model $record): string
    {
        return $record->item?->name ?? 'Barang tidak tercatat';
    }
}
