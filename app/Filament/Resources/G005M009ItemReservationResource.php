<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use App\Models\G005M009ItemReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\G005M009ItemReservationResource\Pages;
use App\Filament\Resources\G005M009ItemReservationResource\RelationManagers;
use App\Filament\Resources\G005M009ItemReservationResource\RelationManagers\ItemReservationDetailRelationManager;

class G005M009ItemReservationResource extends Resource
{
    protected static ?string $model = G005M009ItemReservation::class;

    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?string $slug = 'fdf5ffbe-f082-4e9a-a93f-5ceee206ae49';//'item-reservation';
    protected static ?string $modelLabel = 'Reservasi Barang';
    protected static ?string $navigationLabel = 'Reservasi Barang';

     public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->hasRole(['super_admin', config('role.fasilitas')]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('activity.name')
                            ->label('Kegiatan')
                            ->weight('bold')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('item.name')
                            ->label('Barang')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('start_time')
                            ->label('Waktu Mulai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('end_time')
                            ->label('Waktu Selesai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('returned_at')
                            ->label('Waktu Dikembalikan')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status Reservasi')
                            ->badge()
                            ->inlineLabel(),
                    ])->grow(false),
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
                Forms\Components\Select::make('g004_m008_activity_id')
                    ->relationship('activity', 'name', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->label('Kegiatan'),
                Forms\Components\Select::make('g002_m007_item_id')
                    ->relationship('item', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->searchable()
                    ->preload()
                    ->label('Barang')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time'),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\DateTimePicker::make('returned_at'),
                Forms\Components\TextInput::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity.name')
                    ->label('Kegiatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('activity.unit.name')
                    ->label('Unit')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime('d M Y H:i')
                    ->label('Mulai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime('d M Y H:i')
                    ->label('Selesai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('returned_at')
                    ->dateTime('d M Y H:i')
                    ->label('Dikembalikan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Reservasi')
                    ->badge()
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
            ItemReservationDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG005M009ItemReservations::route('/'),
            'create' => Pages\CreateG005M009ItemReservation::route('/create'),
            'view' => Pages\ViewG005M009ItemReservation::route('/{record}'),
            'edit' => Pages\EditG005M009ItemReservation::route('/{record}/edit'),
        ];
    }
}
