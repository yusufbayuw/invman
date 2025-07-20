<?php

namespace App\Filament\Resources\G002M007ItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemInstanceRelationManager extends RelationManager
{
    protected static string $relationship = 'item_instance';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Barang Satuan';
    protected static ?string $title = 'Barang Satuan';
    protected static ?string $icon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Barang Satuan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                //Tables\Actions\EditAction::make(),
                //Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                //Tables\Actions\BulkActionGroup::make([
                //    Tables\Actions\DeleteBulkAction::make(),
                //]),
            ]);
    }
}
