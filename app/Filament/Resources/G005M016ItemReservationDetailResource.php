<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;
use App\Filament\Resources\G005M016ItemReservationDetailResource\RelationManagers;
use App\Models\G005M016ItemReservationDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G005M016ItemReservationDetailResource extends Resource
{
    protected static ?string $model = G005M016ItemReservationDetail::class;

    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $slug = 'item-reservation-detail';
    protected static ?string $modelLabel = 'Detail Reservasi Barang';
    protected static ?string $navigationLabel = 'Detail Reservasi Barang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g005_m009_item_reservation_id'),
                Forms\Components\TextInput::make('g002_m015_item_instance_id')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g005_m009_item_reservation_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('g002_m015_item_instance_id')
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
            'index' => Pages\ListG005M016ItemReservationDetails::route('/'),
            'create' => Pages\CreateG005M016ItemReservationDetail::route('/create'),
            'view' => Pages\ViewG005M016ItemReservationDetail::route('/{record}'),
            'edit' => Pages\EditG005M016ItemReservationDetail::route('/{record}/edit'),
        ];
    }
}
