<?php

namespace App\Filament\Public\Widgets\Concerns;

use App\Enums\ReservationStatus;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BuildsPublicLoanScheduleTable
{
    protected function visibleReservations(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                ReservationStatus::Approved->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->where('end_time', '>=', now())
            ->orderBy('start_time');
    }

    protected function commonColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('activity.name')
                ->label('Kegiatan')
                ->description(fn (Model $record): string => $record->activity?->description ?: 'Keterangan acara belum tersedia.')
                ->wrap()
                ->searchable(),
            Tables\Columns\TextColumn::make('activity.unit.name')
                ->label('Unit Peminjam')
                ->placeholder('Unit tidak tercatat')
                ->badge()
                ->searchable(),
            Tables\Columns\TextColumn::make('start_time')
                ->label('Mulai')
                ->dateTime('d M Y, H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('end_time')
                ->label('Selesai')
                ->dateTime('d M Y, H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('public_status')
                ->label('Status')
                ->getStateUsing(fn (Model $record): string => $this->publicStatus($record))
                ->badge()
                ->color(fn (string $state): string => $state === 'Sedang Berjalan' ? 'info' : 'success'),
        ];
    }

    protected function detailAction(array $assetEntries): Tables\Actions\ViewAction
    {
        return Tables\Actions\ViewAction::make()
            ->label('Lihat Detail')
            ->modalHeading(fn (Model $record): string => $this->assetName($record))
            ->infolist([
                Section::make('Informasi Aset')
                    ->schema($assetEntries)
                    ->columns(2),
                Section::make('Informasi Kegiatan')
                    ->schema([
                        TextEntry::make('activity.name')->label('Nama Acara')->placeholder('-'),
                        TextEntry::make('activity.unit.name')->label('Unit Peminjam')->placeholder('Unit tidak tercatat'),
                        TextEntry::make('activity.description')->label('Keterangan Acara')->placeholder('Keterangan acara belum tersedia.')->columnSpanFull(),
                        TextEntry::make('start_time')->label('Mulai Digunakan')->dateTime('d M Y, H:i'),
                        TextEntry::make('end_time')->label('Selesai Digunakan')->dateTime('d M Y, H:i'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->getStateUsing(fn (Model $record): string => $this->publicStatus($record))
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Sedang Berjalan' ? 'info' : 'success'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function configurePublicTable(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordAction('view')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('60s')
            ->striped();
    }

    protected function publicStatus(Model $record): string
    {
        if ($record->status === ReservationStatus::CheckedOut->value) {
            return 'Sedang Berjalan';
        }

        if ($record->start_time && $record->end_time && now()->between($record->start_time, $record->end_time)) {
            return 'Sedang Berjalan';
        }

        return 'Disetujui';
    }

    abstract protected function assetName(Model $record): string;
}
