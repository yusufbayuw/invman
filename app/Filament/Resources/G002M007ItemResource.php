<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M007ItemResource\Pages;
use App\Filament\Resources\G002M007ItemResource\RelationManagers;
use App\Models\G002M007Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G002M007ItemResource extends Resource
{
    protected static ?string $model = G002M007Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g001_m001_unit_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g002_m003_item_management_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g002_m002_item_type_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g003_m006_room_id')
                    ->numeric(),
                Forms\Components\TextInput::make('name'),
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
                Tables\Columns\TextColumn::make('g002_m003_item_management_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g002_m002_item_type_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g003_m006_room_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
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
            'index' => Pages\ListG002M007Items::route('/'),
            'create' => Pages\CreateG002M007Item::route('/create'),
            'view' => Pages\ViewG002M007Item::route('/{record}'),
            'edit' => Pages\EditG002M007Item::route('/{record}/edit'),
        ];
    }
}
