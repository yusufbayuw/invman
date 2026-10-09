<?php

namespace App\Filament\Resources;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G005M019VehicleReservationResource\RelationManagers\AssignmentHistoriesRelationManager;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\G008M018Driver;
use App\Models\G005M019VehicleReservation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\G005M019VehicleReservationResource\Pages;
use App\Filament\Resources\G005M019VehicleReservationResource\RelationManagers;

class G005M019VehicleReservationResource extends Resource
{
    protected static ?string $model = G005M019VehicleReservation::class;

    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';
    protected static ?string $slug = 'f8907dc8-c460-41f1-8ea7-b1eac45c2054';//'vehicle-reservation';
    protected static ?string $modelLabel = 'Reservasi Kendaraan';
    protected static ?string $navigationLabel = 'Reservasi Kendaraan';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->isFacility();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · {$record->license_plate}")
                    ->searchable(['name', 'license_plate'])
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('g008_m018_driver_id')
                    ->label('Pengemudi')
                    ->relationship('driver', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->user?->name ?? 'Pengemudi belum memiliki nama')
                    ->searchable()
                    ->preload()
                    ->placeholder('Belum ditentukan'),
                Forms\Components\Select::make('g004_m008_activity_id')
                    ->label('Kegiatan')
                    ->relationship('activity', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Waktu Mulai')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('Waktu Selesai')
                    ->native(false)
                    ->seconds(false)
                    ->after('start_time')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status Reservasi')
                    ->options(ReservationStatus::options())
                    ->default(ReservationStatus::Submitted->value)
                    ->native(false)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('activity.name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('activity.unit.name')
                    ->label('Unit')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Kendaraan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->label('Pengemudi')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('assistant.name')
                    ->label('Kenek (Internal)')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Reservasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ReservationStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (?string $state) => ReservationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('g008_m018_driver_id')
                    ->label('Pengemudi')
                    ->options(fn (): array => G008M018Driver::query()
                        ->with('user')
                        ->get()
                        ->mapWithKeys(fn (G008M018Driver $driver): array => [
                            $driver->id => $driver->user?->name ?? 'Pengemudi tanpa nama',
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Reservasi')
                    ->options(ReservationStatus::options())
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [AssignmentHistoriesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(\App\Services\LoanVisibility::class)
            ->reservations(parent::getEloquentQuery(), auth()->user(), 'vehicle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG005M019VehicleReservations::route('/'),
            'view' => Pages\ViewG005M019VehicleReservation::route('/{record}'),
        ];
    }
}
