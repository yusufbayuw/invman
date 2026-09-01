<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemReviewRelationManager extends RelationManager
{
    protected static string $relationship = 'item_review';

    protected static ?string $title = 'Ulasan Barang';

    protected static ?string $modelLabel = 'Ulasan Barang';

    protected static ?string $icon = 'heroicon-o-star';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Pemberi Ulasan')->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('item_reservation.activity.name')->label('Kegiatan')->placeholder('-'),
                Tables\Columns\TextColumn::make('rating')->label('Rating')->formatStateUsing(fn ($state): string => $state ? $state.' / 5' : '-')->badge()->sortable(),
                Tables\Columns\TextColumn::make('review')->label('Ulasan')->limit(80)->wrap()->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Ulasan diberikan oleh peminjam setelah transaksi.
            ])
            ->actions([
            ])
            ->bulkActions([
                // Ulasan tidak dikelola dari halaman aset.
            ]);
    }
}
