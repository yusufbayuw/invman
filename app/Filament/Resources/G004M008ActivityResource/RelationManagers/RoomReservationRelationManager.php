<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use App\Enums\ReservationStatus;
use App\Models\G005M010RoomReservation;
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

class RoomReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'room_reservation';

    protected static ?string $modelLabel = 'Reservasi Ruangan';

    protected static ?string $title = 'Reservasi Ruangan';

    protected static ?string $icon = 'heroicon-o-building-office';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->room_reservation()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('g004_m008_activity_id')
                    ->default($this->ownerRecord->id ?? null),
                Forms\Components\Select::make('g003_m006_room_id')
                    ->relationship('room', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->name} - {$record->floor->name} - {$record->floor->building->name}";
                    })
                    ->searchable()
                    ->reactive()
                    ->hint(function ($state, Get $get) {
                        $roomId = $state;

                        static $itemOverlappingCache = [];

                        $startTime = $get('start_time');
                        $endTime = $get('end_time');

                        if (! $roomId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (! isset($itemOverlappingCache[$roomId])) {

                            $itemOverlappingCache[$roomId] = G005M010RoomReservation::where('g003_m006_room_id', $roomId)
                                ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                ->where(function ($query) use ($startTime, $endTime) {
                                    $query->where(function ($q) use ($startTime, $endTime) {
                                        $q->where('start_time', '<', $endTime)
                                            ->where('end_time', '>', $startTime);
                                    });
                                })
                                ->exists();
                        }

                        $notAvailable = $itemOverlappingCache[$roomId] ?? 0;

                        if ($notAvailable) {
                            return 'Ruangan ini tidak tersedia pada waktu yang dipilih.';
                        } else {
                            return 'Ruangan ini tersedia.';
                        }
                    })
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (! $value) {
                                    return;
                                }

                                $startTime = $get('start_time');
                                $endTime = $get('end_time');

                                if (! $startTime || ! $endTime) {
                                    return;
                                }

                                $overlap = G005M010RoomReservation::where('g003_m006_room_id', $value)
                                    ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Ruangan ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        },
                    ])
                    ->preload()
                    ->label('Ruangan')
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
                Tables\Columns\TextColumn::make('room.name')
                    ->searchable()
                    ->label('Ruangan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.floor.name')
                    ->searchable()
                    ->label('Lantai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.floor.building.name')
                    ->searchable()
                    ->label('Gedung')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->searchable(),
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
                        'room', $record->getKey(), ReservationStatus::Approved,
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
                        'room', $record->getKey(), ReservationStatus::Rejected, $data['rejection_reason'],
                    )),
                Tables\Actions\Action::make('serahkan')
                    ->label('Pinjamkan')
                    ->color('info')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Approved->value
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'room', $record->getKey(), ReservationStatus::CheckedOut,
                    )),
                Tables\Actions\Action::make('dikembalikan')
                    ->label('Konfirmasi Pengembalian')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::ReturnRequested->value
                        && Auth::user()?->managesReservation($record))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'room', $record->getKey(), ReservationStatus::Returned,
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
