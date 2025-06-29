<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G005M010RoomReservationResource\Pages;
use App\Filament\Resources\G005M010RoomReservationResource\RelationManagers;
use App\Filament\Resources\G005M010RoomReservationResource\RelationManagers\RoomReviewRelationManager;
use App\Models\G005M010RoomReservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G005M010RoomReservationResource extends Resource
{
    protected static ?string $model = G005M010RoomReservation::class;

    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationIcon = 'heroicon-o-bookmark';
    protected static ?string $slug = 'room-reservation';
    protected static ?string $modelLabel = 'Reservasi Ruangan';
    protected static ?string $navigationLabel = 'Reservasi Ruangan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g004_m008_activity_id'),
                Forms\Components\TextInput::make('g003_m006_room_id')
                    ->numeric(),
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
                Tables\Columns\TextColumn::make('g004_m008_activity_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('g003_m006_room_id')
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
            RoomReviewRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG005M010RoomReservations::route('/'),
            'create' => Pages\CreateG005M010RoomReservation::route('/create'),
            'view' => Pages\ViewG005M010RoomReservation::route('/{record}'),
            'edit' => Pages\EditG005M010RoomReservation::route('/{record}/edit'),
        ];
    }
}
