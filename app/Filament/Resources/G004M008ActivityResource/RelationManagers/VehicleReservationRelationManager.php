<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use App\Enums\ReservationStatus;
use App\Models\G005M019VehicleReservation;
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

class VehicleReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicle_reservation';

    protected static ?string $modelLabel = 'Reservasi Kendaraan';

    protected static ?string $title = 'Reservasi Kendaraan';

    protected static ?string $icon = 'heroicon-o-truck';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->vehicle_reservation()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('g004_m008_activity_id')
                    ->default($this->ownerRecord->id ?? null),
                Forms\Components\Select::make('g008_m017_vehicle_id')
                    ->relationship('vehicle', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->name} - {$record->license_plate}";
                    })
                    ->searchable()
                    ->hint(function ($state, Get $get) {
                        $vehicleId = $state;

                        static $itemOverlappingCache = [];

                        if (! $vehicleId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (! isset($itemOverlappingCache[$vehicleId])) {
                            $itemOverlappingCache[$vehicleId] = G005M019VehicleReservation::where('g008_m017_vehicle_id', $vehicleId)
                                ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                ->where(function ($query) use ($get) {
                                    $query->where(function ($q) use ($get) {
                                        $q->where('start_time', '<', $get('end_time'))
                                            ->where('end_time', '>', $get('start_time'));
                                    });
                                })
                                ->exists();
                        }

                        $notAvailable = $itemOverlappingCache[$vehicleId] ?? 0;

                        if ($notAvailable) {
                            return 'Kendaraan ini tidak tersedia pada waktu yang dipilih.';
                        } else {
                            return 'Kendaraan ini tersedia.';
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

                                $overlap = G005M019VehicleReservation::where('g008_m017_vehicle_id', $value)
                                    ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Kendaraan ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        },
                    ])
                    ->required(),
                Forms\Components\Select::make('g008_m018_driver_id')
                    ->relationship('driver', 'id')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->user->name}";
                    })
                    ->hint(
                        function ($state, Get $get) {
                            $driverId = $state;

                            if (! $driverId) {
                                return '';
                            }

                            $vehicleId = $get('g008_m017_vehicle_id');
                            if (! $vehicleId) {
                                return 'Pilih kendaraan terlebih dahulu.';
                            }

                            $overlappingReservations = G005M019VehicleReservation::where('g008_m018_driver_id', $driverId)
                                ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                ->where(function ($query) use ($get) {
                                    $query->where(function ($q) use ($get) {
                                        $q->where('start_time', '<', $get('end_time'))
                                            ->where('end_time', '>', $get('start_time'));
                                    });
                                })
                                ->exists();

                            return $overlappingReservations ? 'Pengemudi ini tidak tersedia pada waktu yang dipilih.' : 'Pengemudi ini tersedia.';
                        }
                    )
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

                                $overlap = G005M019VehicleReservation::where('g008_m018_driver_id', $value)
                                    ->where(fn (Builder $query) => app(LoanAvailabilityService::class)->applyBlockingScope($query))
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Pengemudi ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        },
                    ])
                    ->searchable()
                    ->hidden(fn ($record): bool => ! (
                        Auth::user()
                        && Auth::user()->managesReservation($record)
                    )),
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
                Forms\Components\Hidden::make('status')
                    ->default(fn (): string => $this->ownerRecord->status),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn ($record): string => $record->vehicle?->name ?? 'Reservasi Kendaraan')
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Kendaraan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label('Nomor Polisi'),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->label('Pengemudi')
                    ->placeholder('Belum ditentukan'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray'),
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
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Submitted->value
                        && (! $record->activity?->hold_expires_at || $record->activity->hold_expires_at->isFuture())
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'vehicle', $record->getKey(), ReservationStatus::Approved,
                    )),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Submitted->value
                        && (! $record->activity?->hold_expires_at || $record->activity->hold_expires_at->isFuture())
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record, array $data) => app(LoanRequestService::class)->processReservation(
                        'vehicle', $record->getKey(), ReservationStatus::Rejected, $data['rejection_reason'],
                    )),
                Tables\Actions\Action::make('checkout')
                    ->label('Pinjamkan')
                    ->color('info')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Approved->value
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'vehicle', $record->getKey(), ReservationStatus::CheckedOut,
                    )),
                Tables\Actions\Action::make('return')
                    ->label('Konfirmasi Pengembalian')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::ReturnRequested->value
                        && Auth::user()?->managesReservation($record))
                    ->action(fn ($record) => app(LoanRequestService::class)->processReservation(
                        'vehicle', $record->getKey(), ReservationStatus::Returned,
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
