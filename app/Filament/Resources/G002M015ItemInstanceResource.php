<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M015ItemInstanceResource\Pages;
use App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;
use App\Models\G002M015ItemInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G002M015ItemInstanceResource extends Resource
{
    protected static ?string $model = G002M015ItemInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g002_m007_item_id')
                    ->numeric(),
                Forms\Components\TextInput::make('code'),
                Forms\Components\TextInput::make('status'),
                Forms\Components\Toggle::make('is_available'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g002_m007_item_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
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
            'index' => Pages\ListG002M015ItemInstances::route('/'),
            'create' => Pages\CreateG002M015ItemInstance::route('/create'),
            'view' => Pages\ViewG002M015ItemInstance::route('/{record}'),
            'edit' => Pages\EditG002M015ItemInstance::route('/{record}/edit'),
        ];
    }
}
