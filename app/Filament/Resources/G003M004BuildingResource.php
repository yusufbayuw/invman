<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G003M004BuildingResource\Pages;
use App\Filament\Resources\G003M004BuildingResource\RelationManagers;
use App\Filament\Resources\G003M004BuildingResource\RelationManagers\FloorRelationManager;
use App\Models\G003M004Building;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G003M004BuildingResource extends Resource
{
    protected static ?string $model = G003M004Building::class;

    protected static ?string $navigationGroup = 'Ruangan';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $slug = 'building';
    protected static ?string $modelLabel = 'Bangunan';
    protected static ?string $navigationLabel = 'Bangunan';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Bangunan')
                            ->weight('bold')
                            ->size('lg'),
                        \Filament\Infolists\Components\TextEntry::make('location')
                            ->label('Lokasi')
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
                Forms\Components\TextInput::make('name')
                    ->label('Nama Bangunan'),
                Forms\Components\Textarea::make('location')
                    ->label('Lokasi')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto Bangunan')
                    ->image()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nama Bangunan'),
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto Bangunan')
                    ->circular()
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
            FloorRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG003M004Buildings::route('/'),
            'create' => Pages\CreateG003M004Building::route('/create'),
            'view' => Pages\ViewG003M004Building::route('/{record}'),
            'edit' => Pages\EditG003M004Building::route('/{record}/edit'),
        ];
    }
}
