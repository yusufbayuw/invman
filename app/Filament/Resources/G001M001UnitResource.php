<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G001M001UnitResource\Pages;
use App\Filament\Resources\G001M001UnitResource\RelationManagers;
use App\Filament\Resources\G001M001UnitResource\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\G001M001UnitResource\RelationManagers\ItemRelationManager;
use App\Filament\Resources\G001M001UnitResource\RelationManagers\RoomRelationManager;
use App\Filament\Resources\G001M001UnitResource\RelationManagers\UserRelationManager;
use App\Models\G001M001Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G001M001UnitResource extends Resource
{
    protected static ?string $model = G001M001Unit::class;

    protected static ?string $navigationGroup = 'Manajemen';
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $slug = 'unit';
    protected static ?string $modelLabel = 'Unit';
    protected static ?string $navigationLabel = 'Unit';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Unit')
                            ->weight('bold')
                            ->size('lg'),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ])
                ])->from('md'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
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
            ActivityRelationManager::class,
            ItemRelationManager::class,
            RoomRelationManager::class,
            UserRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG001M001Units::route('/'),
            'create' => Pages\CreateG001M001Unit::route('/create'),
            'view' => Pages\ViewG001M001Unit::route('/{record}'),
            'edit' => Pages\EditG001M001Unit::route('/{record}/edit'),
        ];
    }
}
