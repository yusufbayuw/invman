<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G007M013ItemHistoryResource\Pages;
use App\Filament\Resources\G007M013ItemHistoryResource\RelationManagers;
use App\Models\G007M013ItemHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G007M013ItemHistoryResource extends Resource
{
    protected static ?string $model = G007M013ItemHistory::class;

    protected static ?string $navigationGroup = 'Riwayat';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $slug = 'item-history';
    protected static ?string $modelLabel = 'Riwayat Barang';
    protected static ?string $navigationLabel = 'Riwayat Barang';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('g002_m015_item_instance_id')
                            ->label('ID Barang Satuan')
                            ->weight('bold')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Pengguna')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('action')
                            ->label('Aksi')
                            ->inlineLabel(),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ])
                ])->from('md')->columnSpanFull(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g002_m015_item_instance_id')
                    ->numeric(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\TextInput::make('action'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('photo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g002_m015_item_instance_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->searchable(),
                Tables\Columns\TextColumn::make('photo')
                    ->searchable(),
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
