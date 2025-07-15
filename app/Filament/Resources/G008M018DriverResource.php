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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\TextInput::make('sim_number'),
                Forms\Components\TextInput::make('sim_type'),
                Forms\Components\TextInput::make('vehicle_default')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sim_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sim_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle_default')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListG008M018Drivers::route('/'),
            'create' => Pages\CreateG008M018Driver::route('/create'),
            'view' => Pages\ViewG008M018Driver::route('/{record}'),
            'edit' => Pages\EditG008M018Driver::route('/{record}/edit'),
        ];
    }
}
