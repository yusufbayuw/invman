<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M007ItemResource\Pages;
use App\Filament\Resources\G002M007ItemResource\RelationManagers;
use App\Filament\Resources\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager;
use App\Filament\Resources\G002M007ItemResource\RelationManagers\ItemReservationRelationManager;
use App\Models\G002M007Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G002M007ItemResource extends Resource
{
    protected static ?string $model = G002M007Item::class;

    protected static ?string $navigationGroup = 'Barang';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $slug = 'item';
    protected static ?string $modelLabel = 'Barang';
    protected static ?string $navigationLabel = 'Barang';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('name')
                    ->label('Nama Barang'),
                \Filament\Infolists\Components\TextEntry::make('unit.name')
                    ->label('Unit Pemilik'),
                \Filament\Infolists\Components\TextEntry::make('item_management.name')
                    ->label('Pengelola Barang'),
                \Filament\Infolists\Components\TextEntry::make('item_type.name')
                    ->label('Jenis Barang'),
                \Filament\Infolists\Components\TextEntry::make('room.name')
                    ->label('Ruangan Penempatan'),
                \Filament\Infolists\Components\IconEntry::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean(),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->relationship('unit', 'name')
                    ->label('Unit Pemilik'),
                Forms\Components\Select::make('g002_m003_item_management_id')
                    ->relationship('item_management', 'name')
                    ->label('Pengelola Barang'),
                Forms\Components\Select::make('g002_m002_item_type_id')
                    ->relationship('item_type', 'name')
                    ->label('Jenis Barang'),
                Forms\Components\Select::make('g003_m006_room_id')
                    ->relationship('room', 'name')
                    ->label('Ruangan Penempatan'),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Barang'),
                Forms\Components\TextInput::make('code')
                    ->label('Kode Barang'),
                Forms\Components\Toggle::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->inlineLabel(),
                Forms\Components\TextInput::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit Pemilik')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_management.name')
                    ->label('Pengelola Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_type.name')
                    ->label('Jenis Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan Penempatan')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
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
            ItemInstanceRelationManager::class,
            ItemReservationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG002M007Items::route('/'),
            'create' => Pages\CreateG002M007Item::route('/create'),
            'view' => Pages\ViewG002M007Item::route('/{record}'),
            'edit' => Pages\EditG002M007Item::route('/{record}/edit'),
        ];
    }
}
