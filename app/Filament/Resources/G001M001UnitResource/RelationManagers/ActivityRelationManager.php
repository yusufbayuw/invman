<?php

namespace App\Filament\Resources\G001M001UnitResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activity';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $title = 'Kegiatan';
    protected static ?string $icon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Kegiatan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Nama Kegiatan'),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi Kegiatan'),
                Forms\Components\DateTimePicker::make('start_time')
                    ->required()
                    ->label('Waktu Mulai'),
                Forms\Components\DateTimePicker::make('end_time')
                    ->required()
                    ->label('Waktu Selesai'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kegiatan'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi Kegiatan'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Waktu Mulai'),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Waktu Selesai'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
