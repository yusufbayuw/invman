<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G003M005FloorResource\Pages;
use App\Filament\Resources\G003M005FloorResource\RelationManagers;
use App\Filament\Resources\G003M005FloorResource\RelationManagers\RoomRelationManager;
use App\Models\G003M005Floor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G003M005FloorResource extends Resource
{
    protected static ?string $model = G003M005Floor::class;

    protected static ?string $navigationGroup = 'Ruangan';
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $slug = 'floor';
    protected static ?string $modelLabel = 'Lantai';
    protected static ?string $navigationLabel = 'Lantai';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('building.name')
                            ->label('Gedung')
                            ->weight('bold')
                            ->size('lg'),
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Lantai')
                            ->size('md'),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ]),
                ])->from('md')->columnSpanFull(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g003_m004_building_id')
                    ->relationship('building', 'name')
                    ->label('Gedung'),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Lantai'),
                Forms\Components\FileUpload::make('map')
                    ->label('Denah Lantai')
                    ->image(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lantai')
                    ->searchable(),
                Tables\Columns\TextColumn::make('building.name')
                    ->label('Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('map')
                    ->label('Denah Lantai')
                    ->simpleLightbox(),
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
            RoomRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG003M005Floors::route('/'),
            'create' => Pages\CreateG003M005Floor::route('/create'),
            'view' => Pages\ViewG003M005Floor::route('/{record}'),
            'edit' => Pages\EditG003M005Floor::route('/{record}/edit'),
        ];
    }
}
