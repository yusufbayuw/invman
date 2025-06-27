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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g003_m004_building_id')
                    ->numeric(),
                Forms\Components\TextInput::make('name'),
                Forms\Components\TextInput::make('map'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g003_m004_building_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('map')
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
            'index' => Pages\ListG003M005Floors::route('/'),
            'create' => Pages\CreateG003M005Floor::route('/create'),
            'view' => Pages\ViewG003M005Floor::route('/{record}'),
            'edit' => Pages\EditG003M005Floor::route('/{record}/edit'),
        ];
    }
}
