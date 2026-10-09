<?php

namespace App\Filament\Resources\G005M019VehicleReservationResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssignmentHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'assignmentHistories';
    protected static ?string $title = 'Riwayat Penugasan Personel';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                Tables\Columns\TextColumn::make('oldDriver.user.name')->label('Pengemudi Sebelumnya')->placeholder('-'),
                Tables\Columns\TextColumn::make('newDriver.user.name')->label('Pengemudi Baru')->placeholder('-'),
                Tables\Columns\TextColumn::make('oldAssistant.name')->label('Kenek Sebelumnya')->placeholder('-'),
                Tables\Columns\TextColumn::make('newAssistant.name')->label('Kenek Baru')->placeholder('-'),
                Tables\Columns\TextColumn::make('changed_by_name')->label('Diubah Oleh')->placeholder('Sistem'),
                Tables\Columns\TextColumn::make('reason')->label('Alasan')->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
