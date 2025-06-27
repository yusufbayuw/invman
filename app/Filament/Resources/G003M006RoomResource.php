<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G003M006RoomResource\Pages;
use App\Filament\Resources\G003M006RoomResource\RelationManagers;
use App\Models\G003M006Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G003M006RoomResource extends Resource
{
    protected static ?string $model = G003M006Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g003_m005_floor_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g001_m001_unit_id')
                    ->numeric(),
                Forms\Components\TextInput::make('name'),
                Forms\Components\Toggle::make('is_borrowable'),
                Forms\Components\TextInput::make('capacity')
                    ->numeric(),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('photo'),
                Forms\Components\TextInput::make('qrcode'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g003_m005_floor_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g001_m001_unit_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('photo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('qrcode')
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
            'index' => Pages\ListG003M006Rooms::route('/'),
            'create' => Pages\CreateG003M006Room::route('/create'),
            'view' => Pages\ViewG003M006Room::route('/{record}'),
            'edit' => Pages\EditG003M006Room::route('/{record}/edit'),
        ];
    }
}
