<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G007M014RoomHistoryResource\Pages;
use App\Filament\Resources\G007M014RoomHistoryResource\RelationManagers;
use App\Models\G007M014RoomHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G007M014RoomHistoryResource extends Resource
{
    protected static ?string $model = G007M014RoomHistory::class;

    protected static ?string $navigationGroup = 'Riwayat';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $slug = 'room-history';
    protected static ?string $modelLabel = 'Riwayat Ruangan';
    protected static ?string $navigationLabel = 'Riwayat Ruangan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g003_m006_room_id')
                    ->numeric(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\TextInput::make('action'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('photo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g003_m006_room_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->searchable(),
                Tables\Columns\TextColumn::make('photo')
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
            'index' => Pages\ListG007M014RoomHistories::route('/'),
            'create' => Pages\CreateG007M014RoomHistory::route('/create'),
            'view' => Pages\ViewG007M014RoomHistory::route('/{record}'),
            'edit' => Pages\EditG007M014RoomHistory::route('/{record}/edit'),
        ];
    }
}
