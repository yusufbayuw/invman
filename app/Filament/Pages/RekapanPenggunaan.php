<?php

namespace App\Filament\Pages;

use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;
use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use App\Models\LoanRequestReview;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RekapanPenggunaan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Rekapan Penggunaan';

    protected static ?string $title = 'Rekapan Penggunaan';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.rekapan-penggunaan';

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->isFacility() || auth()->user()->isSarpras());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        return (string) static::baseQuery()
            ->where('status', ReservationStatus::CheckedOut->value)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public function getSubheading(): ?string
    {
        return auth()->user()->isSarpras()
            ? 'Ringkasan pengajuan dan pemakaian aset untuk unit Anda.'
            : 'Analisis penggunaan barang, ruangan, dan kendaraan pada seluruh unit.';
    }

    public function getUsageStats(): array
    {
        $query = $this->getFilteredTableQuery();
        $total = (clone $query)->count();
        $approved = (clone $query)->whereIn('status', [
            ReservationStatus::Approved->value,
            ReservationStatus::PartiallyApproved->value,
            ReservationStatus::CheckedOut->value,
            ReservationStatus::Returned->value,
        ])->count();
        $inUse = (clone $query)->where('status', ReservationStatus::CheckedOut->value)->count();
        $returned = (clone $query)->where('status', ReservationStatus::Returned->value)->count();
        $averageRating = LoanRequestReview::query()
            ->whereIn('g004_m008_activity_id', (clone $query)->select('g004_m008_activities.id'))
            ->avg('rating');

        return [
            [
                'label' => 'Total Pengajuan',
                'value' => number_format($total),
                'description' => 'Sesuai filter aktif',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => 'primary',
            ],
            [
                'label' => 'Tingkat Persetujuan',
                'value' => $total > 0 ? round(($approved / $total) * 100).'%' : '0%',
                'description' => number_format($approved).' pengajuan diterima',
                'icon' => 'heroicon-o-check-badge',
                'color' => 'success',
            ],
            [
                'label' => 'Sedang Digunakan',
                'value' => number_format($inUse),
                'description' => number_format($returned).' penggunaan selesai',
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'color' => 'info',
            ],
            [
                'label' => 'Rata-rata Ulasan',
                'value' => $averageRating ? number_format((float) $averageRating, 1).' / 5' : 'Belum ada',
                'description' => 'Dari penggunaan yang diulas',
                'icon' => 'heroicon-o-star',
                'color' => 'warning',
            ],
        ];
    }

    public function getTableQuery(): Builder
    {
        return static::baseQuery()
            ->with(['user', 'unit', 'groupParent', 'review', 'return_checklist'])
            ->withCount(['item_reservation', 'room_reservation', 'vehicle_reservation'])
            ->withSum('item_reservation as item_quantity', 'quantity');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->modelLabel('Kegiatan')
            ->pluralModelLabel('Kegiatan')
            ->heading('Detail Penggunaan')
            ->description('Gunakan filter untuk mempersempit laporan. Kartu ringkasan di atas mengikuti hasil yang sama.')
            ->columns([
                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->description(fn (G004M008Activity $record): ?string => $record->description)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->summarize(Tables\Columns\Summarizers\Count::make()->label('Jumlah pengajuan')),
                TextColumn::make('group_name')
                    ->label('Kegiatan Bersama')
                    ->state(fn (G004M008Activity $record): string => $record->groupParent?->name ?? $record->name)
                    ->description(fn (G004M008Activity $record): ?string => $record->related_activity_id
                        ? 'Bagian dari kegiatan yang sama'
                        : null)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ReservationStatus::tryFrom($state)?->label() ?? $state ?? '-')
                    ->color(fn (?string $state): string => ReservationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('requirements_summary')
                    ->label('Rincian Aset')
                    ->state(fn (G004M008Activity $record): string => collect([
                        $record->item_reservation_count ? number_format($record->item_quantity ?? 0).' barang' : null,
                        $record->room_reservation_count ? number_format($record->room_reservation_count).' ruangan' : null,
                        $record->vehicle_reservation_count ? number_format($record->vehicle_reservation_count).' kendaraan' : null,
                    ])->filter()->implode(' · ') ?: 'Tidak ada aset')
                    ->icon('heroicon-o-cube')
                    ->wrap(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(function (G004M008Activity $record): string {
                        if (! $record->start_time || ! $record->end_time) {
                            return '-';
                        }

                        $minutes = $record->start_time->diffInMinutes($record->end_time);
                        $hours = intdiv($minutes, 60);
                        $remainingMinutes = $minutes % 60;

                        return collect([
                            $hours ? "{$hours} jam" : null,
                            $remainingMinutes ? "{$remainingMinutes} menit" : null,
                        ])->filter()->implode(' ') ?: '0 menit';
                    })
                    ->icon('heroicon-o-clock')
                    ->toggleable(),
                TextColumn::make('review.rating')
                    ->label('Ulasan')
                    ->formatStateUsing(fn ($state): string => $state ? "{$state} / 5" : '-')
                    ->icon(fn ($state): string => $state ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('return_checklist.is_ok')
                    ->label('Kondisi Kembali')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        true, 1, '1' => 'Baik',
                        false, 0, '0' => 'Perlu tindak lanjut',
                        default => 'Belum diperiksa',
                    })
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        true, 1, '1' => 'success',
                        false, 0, '0' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->since()
                    ->dateTimeTooltip('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')->label('Dari tanggal')->native(false),
                        DatePicker::make('sampai')->label('Sampai tanggal')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('end_time', '>=', $date))
                        ->when($data['sampai'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('start_time', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['dari'] ?? null) {
                            $indicators[] = Indicator::make('Mulai '.Carbon::parse($data['dari'])->translatedFormat('d M Y'))->removeField('dari');
                        }

                        if ($data['sampai'] ?? null) {
                            $indicators[] = Indicator::make('Sampai '.Carbon::parse($data['sampai'])->translatedFormat('d M Y'))->removeField('sampai');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()->isFacility()),
                SelectFilter::make('activity_group')
                    ->label('Kegiatan Bersama')
                    ->options(fn (): array => static::baseQuery()
                        ->whereNull('related_activity_id')
                        ->orderByDesc('created_at')
                        ->limit(200)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $query, $id): Builder => $query
                            ->where(fn (Builder $query): Builder => $query
                                ->whereKey($id)
                                ->orWhere('related_activity_id', $id)))),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ReservationStatus::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label('Pemohon')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('jenis_kebutuhan')
                    ->form([
                        Select::make('jenis')
                            ->label('Jenis kebutuhan')
                            ->options([
                                'item' => 'Barang',
                                'room' => 'Ruangan / Tempat',
                                'vehicle' => 'Kendaraan',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['jenis'] ?? null) {
                            'item' => $query->whereHas('item_reservation'),
                            'room' => $query->whereHas('room_reservation'),
                            'vehicle' => $query->whereHas('vehicle_reservation'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(fn (array $data): ?string => match ($data['jenis'] ?? null) {
                        'item' => 'Jenis: Barang',
                        'room' => 'Jenis: Ruangan / Tempat',
                        'vehicle' => 'Jenis: Kendaraan',
                        default => null,
                    }),
                TernaryFilter::make('sudah_diulas')
                    ->label('Ulasan')
                    ->placeholder('Semua penggunaan')
                    ->trueLabel('Sudah diulas')
                    ->falseLabel('Belum diulas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('review'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('review'),
                    ),
                SelectFilter::make('kondisi_pengembalian')
                    ->label('Kondisi pengembalian')
                    ->options([
                        'good' => 'Kondisi baik',
                        'issue' => 'Perlu tindak lanjut',
                        'unchecked' => 'Belum diperiksa',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'good' => $query->whereHas('return_checklist', fn (Builder $query): Builder => $query->where('is_ok', true)),
                        'issue' => $query->whereHas('return_checklist', fn (Builder $query): Builder => $query->where('is_ok', false)),
                        'unchecked' => $query->whereDoesntHave('return_checklist'),
                        default => $query,
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
            ->persistFiltersInSession()
            ->groups([
                Group::make('unit.name')->label('Unit')->collapsible(),
                Group::make('status')
                    ->label('Status')
                    ->getTitleFromRecordUsing(fn (G004M008Activity $record): string => ReservationStatus::tryFrom($record->status)?->label() ?? $record->status),
                Group::make('start_time')->label('Tanggal penggunaan')->date()->collapsible(),
            ])
            ->headerActions([
                FilamentExportHeaderAction::make('export')
                    ->label('Ekspor Rekapan')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->fileName('rekapan-penggunaan')
                    ->defaultFormat('xlsx')
                    ->disablePdf(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->url(fn (G004M008Activity $record): string => G004M008ActivityResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (G004M008Activity $record): string => G004M008ActivityResource::getUrl('view', ['record' => $record]))
            ->defaultSort('start_time', 'desc')
            ->poll('60s')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Belum ada penggunaan yang sesuai')
            ->emptyStateDescription('Ubah atau hapus filter untuk melihat data penggunaan lainnya.')
            ->emptyStateIcon('heroicon-o-chart-bar-square');
    }

    private static function baseQuery(): Builder
    {
        return app(\App\Services\LoanVisibility::class)
            ->activities(G004M008Activity::query(), auth()->user());
    }
}
