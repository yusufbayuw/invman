<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G008M017VehicleResource\Pages;
use App\Filament\Resources\G008M017VehicleResource\RelationManagers;
use App\Models\G008M017Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G008M017VehicleResource extends Resource
{
    protected static ?string $model = G008M017Vehicle::class;

    protected static ?string $navigationGroup = 'Kendaraan';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $slug = 'vehicle';
    protected static ?string $modelLabel = 'Kendaraan';
    protected static ?string $navigationLabel = 'Kendaraan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g001_m001_unit_id')
                    ->numeric(),
                Forms\Components\TextInput::make('name'),
                Forms\Components\TextInput::make('license_plate'),
                Forms\Components\DatePicker::make('stnk_date'),
                Forms\Components\DatePicker::make('kir_date'),
                Forms\Components\TextInput::make('capacity')
                    ->numeric(),
                Forms\Components\Toggle::make('is_borrowable'),
                Forms\Components\TextInput::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g001_m001_unit_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('license_plate')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stnk_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kir_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->boolean(),
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
            'index' => Pages\ListG008M017Vehicles::route('/'),
            'create' => Pages\CreateG008M017Vehicle::route('/create'),
            'view' => Pages\ViewG008M017Vehicle::route('/{record}'),
            'edit' => Pages\EditG008M017Vehicle::route('/{record}/edit'),
        ];
    }
}
