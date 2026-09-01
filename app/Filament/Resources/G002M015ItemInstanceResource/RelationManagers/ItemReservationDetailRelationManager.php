<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemReservationDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'item_reservation_detail';

    protected static ?string $title = 'Riwayat Peminjaman';

    protected static ?string $modelLabel = 'Detail Peminjaman';

    protected static ?string $icon = 'heroicon-o-calendar-days';

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
                Tables\Columns\TextColumn::make('item_reservation.activity.name')->label('Kegiatan')->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('item_reservation.activity.user.name')->label('Peminjam')->placeholder('-'),
                Tables\Columns\TextColumn::make('item_reservation.start_time')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('item_reservation.end_time')->label('Selesai')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('item_reservation.status')->label('Status')->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Detail ditetapkan melalui proses peminjaman.
            ])
            ->actions([
            ])
            ->bulkActions([
                // Detail transaksi tidak diubah dari halaman aset.
            ]);
    }
}
