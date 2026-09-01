<?php

namespace App\Filament\Resources\G002M007ItemResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'item_reservation';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Peminjaman Barang';

    protected static ?string $title = 'Peminjaman Barang';

    protected static ?string $icon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Peminjaman Barang';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Transaksi dibuat melalui alur pengajuan peminjaman.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('start_time', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('activity.name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity.user.name')
                    ->label('Peminjam')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('returned_at')
                    ->label('Dikembalikan')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum')
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
                    ->url(fn ($record): string => ItemReservationResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                // Transaksi dan audit trail tidak dihapus dari relasi barang.
            ]);
    }
}
