<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G005M009ItemReservationResource\Pages;
use App\Filament\Resources\G005M009ItemReservationResource\RelationManagers;
use App\Filament\Resources\G005M009ItemReservationResource\RelationManagers\ItemReservationDetailRelationManager;
use App\Models\G005M009ItemReservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G005M009ItemReservationResource extends Resource
{
    protected static ?string $model = G005M009ItemReservation::class;

    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?string $slug = 'item-reservation';
    protected static ?string $modelLabel = 'Reservasi Barang';
    protected static ?string $navigationLabel = 'Reservasi Barang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g004_m008_activity_id')
                    ->relationship('activity', 'name', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->label('Kegiatan'),
                Forms\Components\Select::make('g002_m007_item_id')
                    ->relationship('item', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->searchable()
                    ->preload()
                    ->label('Barang')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time'),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\DateTimePicker::make('returned_at'),
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
                Tables\Columns\TextColumn::make('activity.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('item.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('returned_at')
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
            ItemReservationDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG005M009ItemReservations::route('/'),
            'create' => Pages\CreateG005M009ItemReservation::route('/create'),
            'view' => Pages\ViewG005M009ItemReservation::route('/{record}'),
            'edit' => Pages\EditG005M009ItemReservation::route('/{record}/edit'),
        ];
    }
}
