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
    public function callBuildsPublicLoanScheduleTable(string $methodName): void
    {
        // Filament exposes public record getters to Livewire. Guests may use
        // table navigation, but must never invoke getters that return raw models.
        if (! $this->canViewScheduleDetails()) {
            abort_unless(in_array($methodName, [
                '$refresh', 'sortTable', 'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage',
            ], true), 403);
        }
    }

    protected function visibleReservations(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                ReservationStatus::Approved->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->where('end_time', '>=', now());
    }

    protected function canViewScheduleDetails(): bool
    {
        return (bool) auth()->user()?->is_enabled;
    }

    public function getMountedTableAction(): ?Tables\Actions\Action
    {
        // Recheck during rendering too: an open modal snapshot or forged state
        // must not render an infolist after the session becomes anonymous.
        return $this->canViewScheduleDetails() ? parent::getMountedTableAction() : null;
    }

    protected function scheduleText(?string $state): ?string
    {
        if ($this->canViewScheduleDetails() || blank($state)) {
            return $state;
        }

        // Mask before rendering: full values must not reach the guest's HTML,
        // attributes, or Livewire response. Short names must also lose a character.
        $visibleLength = min(3, max(0, mb_strlen($state) - 1));

        return mb_substr($state, 0, $visibleLength).'•••';
    }

    protected function commonColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('activity.name')
                ->label('Kegiatan')
                ->formatStateUsing(fn (?string $state): ?string => $this->scheduleText($state))
                ->description(fn (Model $record): string => $this->canViewScheduleDetails()
                    ? ($record->activity?->description ?: 'Keterangan acara belum tersedia.')
                    : 'Masuk untuk melihat keterangan kegiatan.')
                ->wrap()
                ->searchable($this->canViewScheduleDetails()),
            Tables\Columns\TextColumn::make('activity.unit.name')
                ->label('Unit Peminjam')
                ->placeholder('Unit tidak tercatat')
                ->formatStateUsing(fn (?string $state): ?string => $this->scheduleText($state))
                ->badge()
                ->searchable($this->canViewScheduleDetails()),
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
            ->authorize(fn (): bool => $this->canViewScheduleDetails())
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
            ->recordAction(fn (): ?string => $this->canViewScheduleDetails() ? 'view' : null)
            ->defaultSort('start_time')
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
