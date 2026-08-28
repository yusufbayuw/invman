<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G007M013ItemHistoryResource\Pages;
use App\Models\G007M013ItemHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G007M013ItemHistoryResource extends Resource
{
    protected static ?string $model = G007M013ItemHistory::class;

    protected static ?string $navigationGroup = 'Riwayat';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $slug = 'item-history';

    protected static ?string $modelLabel = 'Riwayat Barang';

    protected static ?string $navigationLabel = 'Riwayat Barang';

    protected static ?int $navigationSort = 10;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Detail Riwayat')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Infolists\Components\TextEntry::make('item_instance.name')
                            ->label('Barang Satuan')
                            ->weight('bold')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('item_instance.code')
                            ->label('Kode Barang')
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
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->label('Barang Satuan')
                    ->relationship('item_instance', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => trim("{$record->name} · {$record->code}", ' ·'))
                    ->searchable(['name', 'code'])
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
                    ->directory('item-history')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->label('Barang Satuan')
                    ->description(fn ($record): ?string => $record->item_instance?->code)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Petugas')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Tindakan / Peristiwa')
                    ->badge()
                    ->color('info')
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
                Tables\Filters\SelectFilter::make('g002_m015_item_instance_id')
                    ->label('Barang Satuan')
                    ->relationship('item_instance', 'name')
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
            'index' => Pages\ListG007M013ItemHistories::route('/'),
            'create' => Pages\CreateG007M013ItemHistory::route('/create'),
            'view' => Pages\ViewG007M013ItemHistory::route('/{record}'),
            'edit' => Pages\EditG007M013ItemHistory::route('/{record}/edit'),
        ];
    }
}
