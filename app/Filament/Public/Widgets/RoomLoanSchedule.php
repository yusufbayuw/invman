<?php

namespace App\Filament\Public\Widgets;

use App\Filament\Public\Widgets\Concerns\BuildsPublicLoanScheduleTable;
use App\Models\G005M010RoomReservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class RoomLoanSchedule extends TableWidget
{
    use BuildsPublicLoanScheduleTable;

    protected static ?int $sort = -80;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $this->configurePublicTable($table)
            ->heading('Ruangan & Tempat')
            ->description('Jadwal peminjaman ruangan yang aktif atau mendatang.')
            ->query($this->visibleReservations(G005M010RoomReservation::query())->with(['room.floor.building', 'activity.unit']))
            ->columns([
                Tables\Columns\TextColumn::make('room.name')->label('Ruangan')->weight('semibold')->searchable()->placeholder('Ruangan tidak tercatat'),
                Tables\Columns\TextColumn::make('location')
                    ->label('Lokasi')
                    ->getStateUsing(fn (Model $record): string => $this->location($record)),
                ...$this->commonColumns(),
            ])
            ->actions([
                $this->detailAction([
                    TextEntry::make('room.name')->label('Ruangan')->placeholder('Ruangan tidak tercatat'),
                    TextEntry::make('location')->label('Lokasi')->getStateUsing(fn (Model $record): string => $this->location($record)),
                ]),
            ])
            ->emptyStateHeading('Belum ada jadwal ruangan')
            ->emptyStateDescription('Tidak ada peminjaman ruangan yang disetujui atau sedang berjalan.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    protected function assetName(Model $record): string
    {
        return $record->room?->name ?? 'Ruangan tidak tercatat';
    }

    private function location(Model $record): string
    {
        return collect([$record->room?->floor?->building?->name, $record->room?->floor?->name])
            ->filter()
            ->join(' · ') ?: 'Lokasi belum tercatat';
    }
}
