<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G008M018DriverResource\Pages;
use App\Filament\Resources\G008M018DriverResource\RelationManagers;
use App\Models\G008M018Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G008M018DriverResource extends Resource
{
    protected static ?string $model = G008M018Driver::class;

    protected static ?string $navigationGroup = 'Kendaraan';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $slug = 'driver';
    protected static ?string $modelLabel = 'Pengemudi';
    protected static ?string $navigationLabel = 'Pengemudi';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return $record?->user?->name ?? 'Pengemudi';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengemudi')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pengguna')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('vehicle_default')
                            ->label('Kendaraan Utama')
                            ->relationship('defaultVehicle', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · {$record->license_plate}")
                            ->searchable(['name', 'license_plate'])
                            ->preload(),
                        Forms\Components\TextInput::make('sim_number')
                            ->label('Nomor SIM')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\Select::make('sim_type')
                            ->label('Jenis SIM')
                            ->options([
                                'A' => 'SIM A',
                                'A Umum' => 'SIM A Umum',
                                'B1' => 'SIM B1',
                                'B1 Umum' => 'SIM B1 Umum',
                                'B2' => 'SIM B2',
                                'B2 Umum' => 'SIM B2 Umum',
                            ])
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Pengemudi')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sim_number')
                    ->label('Nomor SIM')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('sim_type')
                    ->label('Jenis SIM')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('defaultVehicle.name')
                    ->label('Kendaraan Utama')
                    ->description(fn (G008M018Driver $record): ?string => $record->defaultVehicle?->license_plate)
                    ->searchable()
                    ->sortable()
                    ->placeholder('Belum ditentukan'),
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
                Tables\Filters\SelectFilter::make('sim_type')
                    ->label('Jenis SIM')
                    ->options([
                        'A' => 'SIM A',
                        'A Umum' => 'SIM A Umum',
                        'B1' => 'SIM B1',
                        'B1 Umum' => 'SIM B1 Umum',
                        'B2' => 'SIM B2',
                        'B2 Umum' => 'SIM B2 Umum',
                    ]),
                Tables\Filters\SelectFilter::make('vehicle_default')
                    ->label('Kendaraan Utama')
                    ->relationship('defaultVehicle', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('user.name')
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
            'index' => Pages\ListG008M018Drivers::route('/'),
            'create' => Pages\CreateG008M018Driver::route('/create'),
            'view' => Pages\ViewG008M018Driver::route('/{record}'),
            'edit' => Pages\EditG008M018Driver::route('/{record}/edit'),
        ];
    }
}
