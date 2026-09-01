<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'item_history';

    protected static ?string $recordTitleAttribute = 'action';

    protected static ?string $modelLabel = 'Riwayat Barang';

    protected static ?string $title = 'Riwayat Barang';

    protected static ?string $icon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Riwayat Barang';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('action')->label('Aksi')->badge()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Petugas')->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('notes')->label('Catatan')->limit(60)->wrap()->placeholder('-'),
                Tables\Columns\ImageColumn::make('photo')->label('Foto')->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Riwayat dibuat otomatis oleh proses bisnis.
            ])
            ->actions([
            ])
            ->bulkActions([
                // Audit trail tidak dapat diubah atau dihapus.
            ]);
    }
}
