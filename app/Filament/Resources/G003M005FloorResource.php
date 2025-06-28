<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G003M005FloorResource\Pages;
use App\Filament\Resources\G003M005FloorResource\RelationManagers;
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
                Tables\Columns\TextColumn::make('building.name')
                    ->label('Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lantai')
                    ->searchable(),
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
            //
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
