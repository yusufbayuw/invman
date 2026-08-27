<?php

namespace App\Filament\Resources;

use App\Enums\ReservationStatus;
use App\Models\LoanRequestChecklist;
use App\Models\LoanRequestReview;
use App\Services\LoanRequestService;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\G004M008Activity;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\G004M008ActivityResource\Pages;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\ItemReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\VehicleReservationRelationManager;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class G004M008ActivityResource extends Resource
{
    protected static ?string $model = G004M008Activity::class;

    protected static ?string $navigationGroup = 'Kegiatan';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $slug = 'activity';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $navigationLabel = 'Kegiatan';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isSarpras() ? 'Peminjaman Saya' : 'Kegiatan';
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->isSarpras() ? 'Peminjaman' : 'Kegiatan';
    }

    public static function getModelLabel(): string
    {
        return auth()->user()?->isSarpras() ? 'Peminjaman' : 'Kegiatan';
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
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->size('md'),
                        \Filament\Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status Pengajuan')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                            ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray'),
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
                    ->visible(fn ($record) => filled($record->return_checklist)),
                \Filament\Infolists\Components\Section::make('Ulasan Pemohon')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('review.rating')
                            ->label('Rating')
                            ->formatStateUsing(fn ($state) => $state ? "{$state} / 5" : '-'),
                        \Filament\Infolists\Components\TextEntry::make('review.review')
                            ->label('Ulasan')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => filled($record->review)),
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
                    ->beforeOrEqual('end_time')
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
                    ->afterOrEqual('start_time')
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
                    ->simpleLightbox(fn ($record) =>  $record?->attachment ?? null, defaultDisplayUrl: true),
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
                    ->options(ReservationStatus::options()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
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
                        Notification::make()->title('Pengajuan dibatalkan')->success()->send();
                    }),
                Tables\Actions\Action::make('return_checklist')
                    ->label('Checklist Pengembalian')
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
                        LoanRequestChecklist::query()->updateOrCreate(
                            ['g004_m008_activity_id' => $record->id, 'stage' => 'return'],
                            [
                                'user_id' => Auth::id(),
                                'is_ok' => $data['is_ok'],
                                'notes' => $data['notes'] ?? null,
                                'photo' => $data['photo'] ?? null,
                            ],
                        );
                        Notification::make()->title('Checklist pengembalian tersimpan')->success()->send();
                    }),
                Tables\Actions\Action::make('review')
                    ->label('Beri Ulasan')
                    ->icon('heroicon-o-star')
                    ->color('primary')
                    ->visible(fn (G004M008Activity $record) => Auth::user()?->belongsToUnit($record->g001_m001_unit_id)
                        && $record->status === ReservationStatus::Returned->value)
                    ->fillForm(fn (G004M008Activity $record): array => [
                        'rating' => $record->review?->rating,
                        'review' => $record->review?->review,
                    ])
                    ->form([
                        Forms\Components\Select::make('rating')
                            ->label('Rating')
                            ->options([
                                5 => '5 - Sangat Baik',
                                4 => '4 - Baik',
                                3 => '3 - Cukup',
                                2 => '2 - Kurang',
                                1 => '1 - Sangat Kurang',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('review')
                            ->label('Ulasan')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->action(function (G004M008Activity $record, array $data): void {
                        LoanRequestReview::query()->updateOrCreate(
                            ['g004_m008_activity_id' => $record->id],
                            ['user_id' => Auth::id(), 'rating' => $data['rating'], 'review' => $data['review'] ?? null],
                        );
                        Notification::make()->title('Terima kasih atas ulasan Anda')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemReservationRelationManager::class,
            RoomReservationRelationManager::class,
            VehicleReservationRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['return_checklist', 'review'])
            ->withCount(['item_reservation', 'room_reservation', 'vehicle_reservation']);

        if (auth()->user()?->isSarpras()) {
            $query->where('g001_m001_unit_id', auth()->user()->g001_m001_unit_id);
        }

        return $query;
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
