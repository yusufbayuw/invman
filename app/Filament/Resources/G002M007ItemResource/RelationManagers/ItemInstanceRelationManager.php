<?php

namespace App\Filament\Resources\G002M007ItemResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemInstanceRelationManager extends RelationManager
{
    protected static string $relationship = 'item_instance';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'Barang Satuan';

    protected static ?string $title = 'Barang Satuan';

    protected static ?string $icon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Barang Satuan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Barang satuan dikelola dari resource khusus agar jumlah induk tetap tersinkronisasi.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang Satuan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Tersedia')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => ItemInstanceResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record): string => ItemInstanceResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                // Penghapusan dinonaktifkan untuk menjaga histori aset.
            ]);
    }
}
