<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\G005M019VehicleReservation;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class VehicleReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicle_reservation';
    protected static ?string $modelLabel = 'Reservasi Kendaraan';
    protected static ?string $title = 'Reservasi Kendaraan';
    protected static ?string $icon = 'heroicon-o-truck';

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

                        if (!$vehicleId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (!isset($itemOverlappingCache[$vehicleId])) {
                            $itemOverlappingCache[$vehicleId] = G005M019VehicleReservation::where('g008_m017_vehicle_id', $vehicleId)
                                ->where('status', '<>', 'dikembalikan')
                                ->where(function ($query) use ($get) {
                                    $query->where(function ($q) use ($get) {
                                        $q->where('start_time', '<=', $get('end_time'))
                                          ->where('end_time', '>=', $get('start_time'));
                                    });
                                })
                                ->exists();
                        }

                        $notAvailable = $itemOverlappingCache[$vehicleId] ?? 0;

                        if ($notAvailable) {
                            return "Kendaraan ini tidak tersedia pada waktu yang dipilih.";
                        } else {
                            return 'Kendaraan ini tersedia.';
                        }
                    })
                    ->rules([
                         function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (!$value) {
                                    return;
                                }

                                $startTime = $get('start_time');
                                $endTime = $get('end_time');

                                if (!$startTime || !$endTime) {
                                    return;
                                }

                                $overlap = G005M019VehicleReservation::where('g003_m006_room_id', $value)
                                    ->where('status', '<>', 'dikembalikan')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<=', $endTime)
                                              ->where('end_time', '>=', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Kendaraan ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        }
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

                            if (!$driverId) {
                                return '';
                            }

                            $vehicleId = $get('g008_m017_vehicle_id');
                            if (!$vehicleId) {
                                return 'Pilih kendaraan terlebih dahulu.';
                            }

                            $overlappingReservations = G005M019VehicleReservation::where('g008_m018_driver_id', $driverId)
                                ->where(function ($query) use ($get) {
                                    $query->where(function ($q) use ($get) {
                                        $q->where('start_time', '<=', $get('end_time'))
                                          ->where('end_time', '>=', $get('start_time'));
                                    });
                                })
                                ->exists();

                            return $overlappingReservations ? 'Pengemudi ini tidak tersedia pada waktu yang dipilih.' : 'Pengemudi ini tersedia.';
                        }
                    )
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (!$value) {
                                    return;
                                }

                                $startTime = $get('start_time');
                                $endTime = $get('end_time');

                                if (!$startTime || !$endTime) {
                                    return;
                                }

                                $overlap = G005M019VehicleReservation::where('g008_m018_driver_id', $value)
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<=', $endTime)
                                              ->where('end_time', '>=', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Pengemudi ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        }
                    ])
                    ->searchable()
                    ->hidden(fn ($record): bool => !(
                        Auth::user()
                        && Auth::user()->hasRole(['super_admin', config('role.fasilitas')])
                    ))
                    ,
                Flatpickr::make('start_time')
                    ->label('Tanggal dan Waktu Mulai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->default($this->ownerRecord->start_time ?? now())
                    ->minDate(\Carbon\Carbon::parse($this->ownerRecord->start_time)->subMinute() ?? $this->ownerRecord->start_time)
                    ->maxDate(\Carbon\Carbon::parse($this->ownerRecord->end_time)->addMinute() ?? $this->ownerRecord->start_time)
                    ->beforeOrEqual('end_time'),
                Flatpickr::make('end_time')
                    ->label('Tanggal dan Waktu Selesai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->default($this->ownerRecord->end_time ?? now())
                    ->afterOrEqual('start_time')
                    ->minDate(\Carbon\Carbon::parse($this->ownerRecord->start_time)->subMinute() ?? $this->ownerRecord->start_time)
                    ->maxDate(\Carbon\Carbon::parse($this->ownerRecord->end_time)->addMinute() ?? $this->ownerRecord->start_time),
                Forms\Components\TextInput::make('status'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
