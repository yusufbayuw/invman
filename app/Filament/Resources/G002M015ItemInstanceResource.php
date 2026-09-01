<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M015ItemInstanceResource\Pages;
use App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers\ItemChecklistRelationManager;
use App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers\ItemHistoryRelationManager;
use App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers\ItemReservationDetailRelationManager;
use App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers\ItemReviewRelationManager;
use App\Models\G002M015ItemInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G002M015ItemInstanceResource extends Resource
{
    protected static ?string $model = G002M015ItemInstance::class;

    protected static ?string $navigationGroup = 'Barang';

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $slug = 'item-instance';

    protected static ?string $modelLabel = 'Barang Satuan';

    protected static ?string $navigationLabel = 'Barang Satuan';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Barang')
                            ->weight('bold')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('item.name')
                            ->label('Grup Barang')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('code')
                            ->label('Kode Barang Satuan')
                            ->badge()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status Barang')
                            ->badge()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\IconEntry::make('is_available')
                            ->label('Tersedia')
                            ->boolean()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\IconEntry::make('is_borrowable')
                            ->label('Dapat Dipinjam')
                            ->boolean()
                            ->inlineLabel(),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('unit.name')
                            ->label('Unit')
                            ->placeholder('-')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('room.name')
                            ->label('Ruangan')
                            ->placeholder('-')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ]),
                ])->from('md')->columnSpanFull(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g002_m007_item_id')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->relationship('unit', 'name')
                    ->label('Unit')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('g003_m006_room_id')
                    ->relationship('room', 'name')
                    ->label('Ruangan')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')->label('Nama Barang Satuan')->required(),
                Forms\Components\TextInput::make('code')->label('Kode')->required(),
                Forms\Components\TextInput::make('status')->label('Status'),
                Forms\Components\Toggle::make('is_available')
                    ->label('Tersedia')
                    ->default(true),
                Forms\Components\Toggle::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nama Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item.name')
                    ->searchable()
                    ->label('Grup Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Barang Satuan')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Barang')
                    ->badge()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Tersedia')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Histori barang satuan dipertahankan; nonaktifkan status pinjam untuk memensiunkan aset.
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemHistoryRelationManager::class,
            ItemReservationDetailRelationManager::class,
            ItemReviewRelationManager::class,
            ItemChecklistRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG002M015ItemInstances::route('/'),
            'create' => Pages\CreateG002M015ItemInstance::route('/create'),
            'view' => Pages\ViewG002M015ItemInstance::route('/{record}'),
            'edit' => Pages\EditG002M015ItemInstance::route('/{record}/edit'),
        ];
    }
}
