<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G007M021VehicleHistoryResource\Pages;
use App\Models\G007M021VehicleHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G007M021VehicleHistoryResource extends Resource
{
    protected static ?string $model = G007M021VehicleHistory::class;

    protected static ?string $navigationGroup = 'Riwayat';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $slug = 'vehicle-history';

    protected static ?string $modelLabel = 'Riwayat Kendaraan';

    protected static ?string $navigationLabel = 'Riwayat Kendaraan';

    protected static ?int $navigationSort = 30;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Detail Riwayat')
                ->icon('heroicon-o-clock')
                ->schema([
                    Infolists\Components\TextEntry::make('vehicle.name')
                        ->label('Kendaraan')
                        ->weight('bold')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('vehicle.license_plate')
                        ->label('Nomor Polisi')
                        ->badge()
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('action')
                        ->label('Tindakan / Peristiwa')
                        ->badge()
                        ->color('info')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('user.name')
                        ->label('Petugas')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Waktu Kejadian')
                        ->dateTime('d M Y, H:i'),
                    Infolists\Components\TextEntry::make('notes')
                        ->label('Catatan')
                        ->placeholder('Tidak ada catatan')
                        ->columnSpanFull(),
                    Infolists\Components\ImageEntry::make('photo')
                        ->label('Foto Dokumentasi')
                        ->height(220)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · {$record->license_plate}")
                    ->searchable(['name', 'license_plate'])
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Petugas')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('action')
                    ->label('Tindakan / Peristiwa')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto Dokumentasi')
                    ->image()
                    ->directory('vehicle-history')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Kendaraan')
                    ->description(fn ($record): ?string => $record->vehicle?->license_plate)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Petugas')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Tindakan / Peristiwa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('-'),
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->square(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Kejadian')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListG007M021VehicleHistories::route('/'),
            'create' => Pages\CreateG007M021VehicleHistory::route('/create'),
            'view' => Pages\ViewG007M021VehicleHistory::route('/{record}'),
            'edit' => Pages\EditG007M021VehicleHistory::route('/{record}/edit'),
        ];
    }
}
