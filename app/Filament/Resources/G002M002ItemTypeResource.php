<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M002ItemTypeResource\Pages;
use App\Filament\Resources\G002M002ItemTypeResource\RelationManagers;
use App\Models\G002M002ItemType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G002M002ItemTypeResource extends Resource
{
    protected static ?string $model = G002M002ItemType::class;

    protected static ?string $navigationGroup = 'Barang';
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $slug = 'item-type';
    protected static ?string $modelLabel = 'Jenis Barang';
    protected static ?string $navigationLabel = 'Jenis Barang';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('name')
                    ->label('Nama'),
                \Filament\Infolists\Components\TextEntry::make('description')
                    ->label('Deskripsi'),
                \Filament\Infolists\Components\TextEntry::make('created_at')
                    ->label('Dibuat')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('updated_at')
                    ->label('Diubah')
                    ->dateTime(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Jenis Barang'),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
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
            'index' => Pages\ListG002M002ItemTypes::route('/'),
            'create' => Pages\CreateG002M002ItemType::route('/create'),
            'view' => Pages\ViewG002M002ItemType::route('/{record}'),
            'edit' => Pages\EditG002M002ItemType::route('/{record}/edit'),
        ];
    }
}
