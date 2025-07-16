<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
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
        return Auth::user()->hasRole(['super_admin', config('role.fasilitas')]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g008_m017_vehicle_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g008_m018_driver_id')
                    ->numeric(),
                Forms\Components\Select::make('g004_m008_activity_id')
                    ->relationship('activity', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time'),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\TextInput::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('g008_m017_vehicle_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g008_m018_driver_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG005M019VehicleReservations::route('/'),
            'create' => Pages\CreateG005M019VehicleReservation::route('/create'),
            'view' => Pages\ViewG005M019VehicleReservation::route('/{record}'),
            'edit' => Pages\EditG005M019VehicleReservation::route('/{record}/edit'),
        ];
    }
}
