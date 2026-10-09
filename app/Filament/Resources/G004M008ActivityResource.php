<?php

namespace App\Filament\Resources;

use App\Enums\ReservationStatus;
use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Resources\G004M008ActivityResource\Pages;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\ItemReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\VehicleReservationRelationManager;
use App\Models\G004M008Activity;
use App\Services\LoanRequestService;
use App\Services\LoanVisibility;
use App\Services\LoanReviewService;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class G004M008ActivityResource extends Resource
{
    protected static ?string $model = G004M008Activity::class;

    protected static ?string $navigationGroup = 'Kegiatan';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $slug = 'activity';

    protected static ?string $modelLabel = 'Kegiatan';

    protected static ?string $navigationLabel = 'Kegiatan';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 15;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'notes', 'user.name', 'unit.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Unit' => $record->unit?->name ?? '-',
            'Pemohon' => $record->user?->name ?? '-',
            'Status' => ReservationStatus::tryFrom($record->status)?->label() ?? $record->status,
            'Jadwal' => $record->start_time?->translatedFormat('d M Y, H:i') ?? '-',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return app(LoanVisibility::class)->activities(parent::getGlobalSearchEloquentQuery()->with(['user', 'unit']), auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', ReservationStatus::Submitted->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengajuan yang menunggu persetujuan';
    }

    public static function getNavigationLabel(): string
    {
        return 'Kegiatan';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Kegiatan';
    }

    public static function getModelLabel(): string
    {
        return 'Kegiatan';
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Kegiatan')
                            ->weight('bold')
                            ->size('lg'),
                        \Filament\Infolists\Components\TextEntry::make('groupParent.name')
                            ->label('Terkait Kegiatan Induk')
                            ->icon('heroicon-o-link')
                            ->url(fn (G004M008Activity $record): ?string => $record->related_activity_id
                                ? static::getUrl('view', ['record' => $record->related_activity_id])
                                : null)
                            ->visible(fn (G004M008Activity $record): bool => filled($record->related_activity_id)
                                && static::canViewFullActivity($record)),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->size('md')
                            ->visible(fn (G004M008Activity $record): bool => static::canViewFullActivity($record)),
                        \Filament\Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->visible(fn (G004M008Activity $record): bool => static::canViewFullActivity($record)),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status Pengajuan')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                            ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray'),
                        \Filament\Infolists\Components\TextEntry::make('hold_expires_at')
                            ->label('Batas Persetujuan')
                            ->dateTime('d M Y, H:i')
                            ->helperText('Kebutuhan yang masih menunggu akan dilepaskan setelah waktu ini.')
                            ->visible(fn (G004M008Activity $record): bool => $record->status === ReservationStatus::Submitted->value),
                        \Filament\Infolists\Components\TextEntry::make('expired_at')
                            ->label('Hold Dilepaskan')
                            ->dateTime('d M Y, H:i')
                            ->visible(fn (G004M008Activity $record): bool => filled($record->expired_at)),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Diajukan Oleh')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('unit.name')
                            ->label('Unit')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('start_time')
                            ->label('Tanggal dan Waktu Mulai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('end_time')
                            ->label('Tanggal dan Waktu Selesai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('attachment')
                            ->label('Lampiran')
                            ->inlineLabel()
                            ->visible(fn (G004M008Activity $record): bool => static::canViewFullActivity($record)),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ]),
                ])->from('md')->columnSpanFull(),
                \Filament\Infolists\Components\Section::make('Pengajuan Lain untuk Kegiatan Ini')
                    ->description('Semua pengajuan tetap memiliki jadwal, keputusan pengelola dan histori masing-masing.')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('linkedRequests')
                            ->hiddenLabel()
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Kegiatan')
                                    ->url(fn (G004M008Activity $record): string => static::getUrl('view', ['record' => $record])),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status Pengajuan')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => ReservationStatus::tryFrom($state)?->label() ?? $state ?? '-'),
                                \Filament\Infolists\Components\TextEntry::make('start_time')
                                    ->label('Jadwal Mulai')
                                    ->dateTime('d M Y H:i'),
                                \Filament\Infolists\Components\TextEntry::make('notes')
                                    ->label('Catatan Khusus')
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (G004M008Activity $record): bool => ! $record->related_activity_id
                        && static::canViewFullActivity($record)
                        && $record->linkedRequests()->exists()),
                \Filament\Infolists\Components\Section::make('Checklist Pengembalian')
                    ->schema([
                        \Filament\Infolists\Components\IconEntry::make('return_checklist.is_ok')
                            ->label('Kondisi baik')
                            ->boolean(),
                        \Filament\Infolists\Components\TextEntry::make('return_checklist.notes')
                            ->label('Catatan')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('return_checklist.created_at')
                            ->label('Diisi pada')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->visible(fn (G004M008Activity $record): bool => static::canViewFullActivity($record)
                        && filled($record->return_checklist)),
                \Filament\Infolists\Components\Section::make('Ulasan Pemohon')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('review.rating')
                            ->label('Rating')
                            ->formatStateUsing(fn ($state) => $state ? "{$state} / 5" : '-'),
                        \Filament\Infolists\Components\TextEntry::make('review.review')
                            ->label('Ulasan')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\RepeatableEntry::make('item_reviews')
                            ->label('Ulasan Barang')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('item_instance.name')
                                    ->label('Barang satuan')
                                    ->hint(fn ($record): ?string => $record->item_instance?->code),
                                \Filament\Infolists\Components\TextEntry::make('rating')
                                    ->label('Rating')
                                    ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} / 5" : '-'),
                                \Filament\Infolists\Components\TextEntry::make('review')
                                    ->label('Ulasan')
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\RepeatableEntry::make('room_reviews')
                            ->label('Ulasan Ruangan')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('room.name')->label('Ruangan'),
                                \Filament\Infolists\Components\TextEntry::make('rating')
                                    ->label('Rating')
                                    ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} / 5" : '-'),
                                \Filament\Infolists\Components\TextEntry::make('review')
                                    ->label('Ulasan')
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\RepeatableEntry::make('vehicle_reviews')
                            ->label('Ulasan Kendaraan')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('vehicle.name')
                                    ->label('Kendaraan')
                                    ->hint(fn ($record): ?string => $record->vehicle?->license_plate),
                                \Filament\Infolists\Components\TextEntry::make('rating')
                                    ->label('Rating')
                                    ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} / 5" : '-'),
                                \Filament\Infolists\Components\TextEntry::make('review')
                                    ->label('Ulasan')
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (G004M008Activity $record): bool => static::canViewFullActivity($record)
                        && (filled($record->review)
                            || $record->item_reviews->isNotEmpty()
                            || $record->room_reviews->isNotEmpty()
                            || $record->vehicle_reviews->isNotEmpty())),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->default(auth()?->user()?->id ?? null)
                    ->searchable()
                    ->preload()
                    ->label('Diajukan Oleh')
                    ->disabled(fn () => auth()->user()?->isSarpras())
                    ->dehydrated()
                    ->required(),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->label('Unit Penyelenggara')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->default(auth()?->user()?->g001_m001_unit_id ?? null)
                    ->preload()
                    ->disabled(fn () => auth()->user()?->isSarpras())
                    ->dehydrated()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Kegiatan')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi Kegiatan')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan Tambahan')
                    ->columnSpanFull(),
                Flatpickr::make('start_time')
                    ->label('Tanggal dan Waktu Mulai')
                    ->time(true)
                    ->seconds(false)
                    ->live()
                    ->time24hr(true)
                    ->before('end_time')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $set('end_time', \Carbon\Carbon::parse($state)->addHour());
                        }
                    }),
                Flatpickr::make('end_time')
                    ->label('Tanggal dan Waktu Selesai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->after('start_time')
                    ->minDate(fn (Get $get) => $get('start_time') ? \Carbon\Carbon::parse($get('start_time'))->addMinute() : now()),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Lampiran (jika ada)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kegiatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requirements_summary')
                    ->label('Kebutuhan')
                    ->state(function (G004M008Activity $record): string {
                        return collect([
                            $record->item_reservation_count ? "{$record->item_reservation_count} barang" : null,
                            $record->room_reservation_count ? "{$record->room_reservation_count} ruang/tempat" : null,
                            $record->vehicle_reservation_count ? "{$record->vehicle_reservation_count} kendaraan" : null,
                        ])->filter()->implode(' · ') ?: '-';
                    }),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hold_expires_at')
                    ->label('Batas Hold')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Diajukan Oleh')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment')
                    ->label('Lampiran')
                    ->formatStateUsing(fn ($record) => $record->attachment ? 'file' : null)
                    ->simpleLightbox(fn ($record) => $record?->attachment ?? null, defaultDisplayUrl: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ReservationStatus::options())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => Auth::user()?->isFacility() ?? false),
                Tables\Filters\Filter::make('jadwal_aktif')
                    ->label('Sedang berlangsung hari ini')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('start_time', '<=', today())
                        ->whereDate('end_time', '>=', today()))
                    ->toggle(),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->groups([
                \Filament\Tables\Grouping\Group::make('unit.name')->label('Unit')->collapsible(),
                \Filament\Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->getTitleFromRecordUsing(fn (G004M008Activity $record): string => ReservationStatus::tryFrom($record->status)?->label() ?? $record->status),
                \Filament\Tables\Grouping\Group::make('start_time')->label('Tanggal mulai')->date()->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->recordUrl(fn (G004M008Activity $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada pengajuan')
            ->emptyStateDescription('Pengajuan baru akan muncul di sini setelah dikirim oleh pemohon.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->url(fn (G004M008Activity $record): string => AjukanPeminjaman::getUrl(['record' => $record->id]))
                    ->visible(fn (G004M008Activity $record) => Auth::user()?->can('update', $record)),
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Pengajuan dan seluruh kebutuhannya akan dibatalkan.')
                    ->visible(fn (G004M008Activity $record) => Auth::user()?->belongsToUnit($record->g001_m001_unit_id)
                        && $record->status === ReservationStatus::Submitted->value)
                    ->action(function (G004M008Activity $record): void {
                        app(LoanRequestService::class)->cancel($record);
                        Notification::make()
                            ->title('Pengajuan dibatalkan')
                            ->body('Seluruh kebutuhan dibatalkan dan pengelola aset telah diberi tahu.')
                            ->warning()
                            ->icon('heroicon-o-no-symbol')
                            ->seconds(7)
                            ->send();
                    }),
                Tables\Actions\Action::make('return_checklist')
                    ->label('Ajukan Pengembalian')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (G004M008Activity $record) => Auth::user()?->belongsToUnit($record->g001_m001_unit_id)
                        && $record->status === ReservationStatus::CheckedOut->value)
                    ->fillForm(fn (G004M008Activity $record): array => [
                        'is_ok' => $record->return_checklist?->is_ok ?? true,
                        'notes' => $record->return_checklist?->notes,
                        'photo' => $record->return_checklist?->photo,
                    ])
                    ->form([
                        Forms\Components\Toggle::make('is_ok')
                            ->label('Semua aset dalam kondisi baik')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan kondisi')
                            ->required(fn (Forms\Get $get) => ! $get('is_ok'))
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto pengembalian')
                            ->directory('loan-return-checklists')
                            ->image()
                            ->maxSize(5120),
                    ])
                    ->action(function (G004M008Activity $record, array $data): void {
                        app(LoanRequestService::class)->requestReturn($record, $data);
                        Notification::make()
                            ->title('Pengembalian diajukan')
                            ->body($data['is_ok']
                                ? 'Checklist tersimpan dan menunggu konfirmasi pengelola aset.'
                                : 'Catatan kondisi tersimpan dan menunggu konfirmasi pengelola aset.')
                            ->status($data['is_ok'] ? 'success' : 'warning')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->seconds(7)
                            ->send();
                    }),
                Tables\Actions\Action::make('review')
                    ->label('Beri Ulasan')
                    ->icon('heroicon-o-star')
                    ->color('primary')
                    ->visible(fn (G004M008Activity $record): bool => app(LoanReviewService::class)->canReview($record, Auth::user()))
                    ->fillForm(fn (G004M008Activity $record): array => app(LoanReviewService::class)->formData($record))
                    ->form([
                        Forms\Components\Section::make('Pengalaman peminjaman secara keseluruhan')
                            ->schema(static::reviewFields('overall'))
                            ->columns(2),
                        Forms\Components\Section::make('Barang yang digunakan')
                            ->schema([static::assetReviewRepeater('items', includeItemInstance: true)])
                            ->visible(fn (Forms\Get $get): bool => filled($get('items'))),
                        Forms\Components\Section::make('Ruangan yang digunakan')
                            ->schema([static::assetReviewRepeater('rooms')])
                            ->visible(fn (Forms\Get $get): bool => filled($get('rooms'))),
                        Forms\Components\Section::make('Kendaraan yang digunakan')
                            ->schema([static::assetReviewRepeater('vehicles')])
                            ->visible(fn (Forms\Get $get): bool => filled($get('vehicles'))),
                    ])
                    ->modalWidth('5xl')
                    ->action(function (G004M008Activity $record, array $data): void {
                        $user = Auth::user();
                        abort_unless($user, 403);
                        app(LoanReviewService::class)->save($record, $user, $data);
                        Notification::make()
                            ->title('Terima kasih atas ulasan Anda')
                            ->body('Ulasan peminjaman dan aset berhasil disimpan.')
                            ->success()
                            ->icon('heroicon-o-star')
                            ->seconds(6)
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            ItemReservationRelationManager::class,
            RoomReservationRelationManager::class,
            VehicleReservationRelationManager::class,
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private static function reviewFields(string $prefix = ''): array
    {
        $field = fn (string $name): string => $prefix !== '' ? "{$prefix}.{$name}" : $name;

        return [
            Forms\Components\Select::make($field('rating'))
                ->label('Rating (opsional)')
                ->options([
                    5 => '5 - Sangat Baik',
                    4 => '4 - Baik',
                    3 => '3 - Cukup',
                    2 => '2 - Kurang',
                    1 => '1 - Sangat Kurang',
                ])
                ->native(false),
            Forms\Components\Textarea::make($field('review'))
                ->label('Ulasan (opsional)')
                ->maxLength(2000)
                ->rows(3),
        ];
    }

    private static function assetReviewRepeater(string $name, bool $includeItemInstance = false): Forms\Components\Repeater
    {
        $hiddenFields = [Forms\Components\Hidden::make('reservation_id')];
        if ($includeItemInstance) {
            $hiddenFields[] = Forms\Components\Hidden::make('item_instance_id');
        }

        return Forms\Components\Repeater::make($name)
            ->label('')
            ->schema([
                ...$hiddenFields,
                Forms\Components\TextInput::make('label')
                    ->label('Aset')
                    ->disabled()
                    ->dehydrated(false),
                ...static::reviewFields(),
            ])
            ->columns(3)
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->defaultItems(0);
    }

    private static function canViewFullActivity(G004M008Activity $activity): bool
    {
        $user = auth()->user();

        return $user && ($user->isFacility() || $user->belongsToUnit($activity->g001_m001_unit_id));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'return_checklist',
                'groupParent',
                'linkedRequests',
                'review',
                'item_reviews.item_instance',
                'room_reviews.room',
                'vehicle_reviews.vehicle',
            ])
            ->withCount(['item_reservation', 'room_reservation', 'vehicle_reservation']);

        return app(LoanVisibility::class)->activities($query, auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG004M008Activities::route('/'),
            'create' => Pages\CreateG004M008Activity::route('/create'),
            'view' => Pages\ViewG004M008Activity::route('/{record}'),
            'edit' => Pages\EditG004M008Activity::route('/{record}/edit'),
        ];
    }
}
