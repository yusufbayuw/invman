<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use App\Enums\ReservationStatus;
use App\Models\G002M007Item;
use App\Models\G005M009ItemReservation;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ItemReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'item_reservation';

    protected static ?string $modelLabel = 'Reservasi Barang';

    protected static ?string $title = 'Reservasi Barang';

    protected static ?string $icon = 'heroicon-o-bookmark-square';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->item_reservation()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('g004_m008_activity_id')
                    ->default($this->ownerRecord->id ?? null),
                Forms\Components\Select::make('g002_m007_item_id')
                    ->relationship('item', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->label('Barang')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->default(1)
                    ->numeric()
                    ->hint(function (Get $get) {
                        $itemId = $get('g002_m007_item_id');
                        static $itemCache = [];
                        static $itemOverlappingCache = [];

                        if (! $itemId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (! isset($itemCache[$itemId])) {
                            $itemCache[$itemId] = G002M007Item::find($itemId);
                            $itemOverlappingCache[$itemId] = G005M009ItemReservation::where('g002_m007_item_id', $itemId)
                                ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                ->where('start_time', '<', $get('end_time'))
                                ->where('end_time', '>', $get('start_time'))
                                ->sum('quantity');
                        }

                        $available = ($itemCache[$itemId]?->available_quantity - $itemOverlappingCache[$itemId] ?? 0) ?? 0;

                        return "Saat ini tersedia: {$available}";
                    })
                    ->maxValue(function (Get $get) {
                        $itemId = $get('g002_m007_item_id');
                        static $itemCache = [];
                        static $itemOverlappingCache = [];

                        if (! $itemId) {
                            return 0;
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (! isset($itemCache[$itemId])) {
                            $itemCache[$itemId] = G002M007Item::find($itemId);
                            $itemOverlappingCache[$itemId] = G005M009ItemReservation::where('g002_m007_item_id', $itemId)
                                ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                ->where('start_time', '<', $get('end_time'))
                                ->where('end_time', '>', $get('start_time'))
                                ->sum('quantity');
                        }

                        $available = ($itemCache[$itemId]?->available_quantity - $itemOverlappingCache[$itemId] ?? 0) ?? 0;

                        return $available;
                    })
                    ->minValue(1)
                    ->label('Jumlah Barang')
                    ->required(),
                Flatpickr::make('start_time')
                    ->label('Tanggal dan Waktu Mulai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->default($this->ownerRecord->start_time ?? now())
                    ->minDate(\Carbon\Carbon::parse($this->ownerRecord->start_time)->subMinute() ?? $this->ownerRecord->start_time)
                    ->maxDate(\Carbon\Carbon::parse($this->ownerRecord->end_time)->addMinute() ?? $this->ownerRecord->start_time)
                    ->before('end_time'),
                Flatpickr::make('end_time')
                    ->label('Tanggal dan Waktu Selesai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->default($this->ownerRecord->end_time ?? now())
                    ->after('start_time')
                    ->minDate(\Carbon\Carbon::parse($this->ownerRecord->start_time)->subMinute() ?? $this->ownerRecord->start_time)
                    ->maxDate(\Carbon\Carbon::parse($this->ownerRecord->end_time)->addMinute() ?? $this->ownerRecord->start_time),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Nama Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->label('Waktu Mulai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->label('Waktu Selesai'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->label('Alasan Penolakan')
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('statusChangedBy.name')
                    ->label('Status Diubah Oleh')
                    ->placeholder('Sistem')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status_changed_at')
                    ->label('Waktu Perubahan')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => Auth::user()?->isFacility()
                        && $this->ownerRecord->status === ReservationStatus::Draft->value),
            ])
            ->actions([
                Tables\Actions\Action::make('konfirmasi')
                    ->label('Setujui')
                    ->color('success')
                    ->hidden(fn ($record): bool => ! (
                        $record->status === ReservationStatus::Submitted->value
                        && (! $record->activity?->hold_expires_at || $record->activity->hold_expires_at->isFuture())
                        && Auth::user()
                        && Auth::user()->managesReservation($record)
                    ))
                    ->icon('heroicon-o-check-circle')
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'item', $record->getKey(), ReservationStatus::Approved,
                    )),
                Tables\Actions\Action::make('ditolak')
                    ->label('Tolak')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->hidden(fn ($record): bool => ! (
                        $record->status === ReservationStatus::Submitted->value
                        && (! $record->activity?->hold_expires_at || $record->activity->hold_expires_at->isFuture())
                        && Auth::user()
                        && Auth::user()->managesReservation($record)
                    ))
                    ->icon('heroicon-o-x-circle')
                    ->action(fn ($record, array $data) => app(LoanRequestService::class)->processReservation(
                        'item', $record->getKey(), ReservationStatus::Rejected, $data['rejection_reason'],
                    )),
                Tables\Actions\Action::make('serahkan')
                    ->label('Pinjamkan')
                    ->color('info')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Approved->value
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'item', $record->getKey(), ReservationStatus::CheckedOut,
                    )),
                Tables\Actions\Action::make('dikembalikan')
                    ->label('Konfirmasi Pengembalian')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::ReturnRequested->value
                        && Auth::user()?->managesReservation($record))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'item', $record->getKey(), ReservationStatus::Returned,
                    )),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->isFacility()
                        && $this->ownerRecord->status === ReservationStatus::Draft->value),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->isFacility()
                        && $this->ownerRecord->status === ReservationStatus::Draft->value),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn () => Auth::user()?->isFacility()
                    && $this->ownerRecord->status === ReservationStatus::Draft->value),
            ]);
    }
}
