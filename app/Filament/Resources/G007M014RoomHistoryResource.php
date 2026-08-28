<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G007M014RoomHistoryResource\Pages;
use App\Models\G007M014RoomHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G007M014RoomHistoryResource extends Resource
{
    protected static ?string $model = G007M014RoomHistory::class;

    protected static ?string $navigationGroup = 'Riwayat';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'room-history';

    protected static ?string $modelLabel = 'Riwayat Ruangan';

    protected static ?string $navigationLabel = 'Riwayat Ruangan';

    protected static ?int $navigationSort = 20;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Detail Riwayat')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Infolists\Components\TextEntry::make('room.name')
                            ->label('Ruangan')
                            ->weight('bold')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('room.floor.name')
                            ->label('Lantai')
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
                Forms\Components\Select::make('g003_m006_room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name')
                    ->searchable()
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
                    ->directory('room-history')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->description(fn ($record): ?string => $record->room?->floor?->name)
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
                Tables\Filters\SelectFilter::make('g003_m006_room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name')
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
            'index' => Pages\ListG007M014RoomHistories::route('/'),
            'create' => Pages\CreateG007M014RoomHistory::route('/create'),
            'view' => Pages\ViewG007M014RoomHistory::route('/{record}'),
            'edit' => Pages\EditG007M014RoomHistory::route('/{record}/edit'),
        ];
    }
}
