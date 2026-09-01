<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'item_instance_checklist';

    protected static ?string $title = 'Checklist Kondisi';

    protected static ?string $modelLabel = 'Checklist Kondisi';

    protected static ?string $icon = 'heroicon-o-clipboard-document-check';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('checklist_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('checklist_date')->label('Tanggal Pemeriksaan')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Pemeriksa')->searchable()->placeholder('-'),
                Tables\Columns\IconColumn::make('is_ok')->label('Kondisi Baik')->boolean(),
                Tables\Columns\TextColumn::make('notes')->label('Catatan')->limit(60)->wrap()->placeholder('-'),
                Tables\Columns\ImageColumn::make('photo')->label('Foto')->toggleable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Belum ada checklist kondisi')
            ->emptyStateDescription('Hasil pemeriksaan kondisi barang satuan akan tampil di sini.');
    }
}
