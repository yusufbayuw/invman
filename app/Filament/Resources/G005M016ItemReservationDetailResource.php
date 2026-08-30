<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;
use App\Models\G005M016ItemReservationDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class G005M016ItemReservationDetailResource extends Resource
{
    protected static ?string $model = G005M016ItemReservationDetail::class;

    protected static ?string $navigationGroup = 'Peminjaman';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $slug = 'item-reservation-detail';

    protected static ?string $modelLabel = 'Detail Reservasi Barang';

    protected static ?string $navigationLabel = 'Detail Reservasi Barang';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('g005_m009_item_reservation_id')
                            ->label('ID Reservasi Barang')
                            ->weight('bold')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('g002_m015_item_instance_id')
                            ->label('Instansi Barang')
                            ->inlineLabel(),
                    ]),
                    \Filament\Infolists\Components\Section::make([
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
                Forms\Components\Select::make('g005_m009_item_reservation_id')
                    ->relationship('item_reservation', 'id', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    ->searchable(),
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->relationship('item_instance', 'id', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->searchable()
                    ->preload()
                    ->label('Instansi Barang'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g005_m009_item_reservation_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->label('Barang')
                    ->sortable(),
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
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListG005M016ItemReservationDetails::route('/'),
            'view' => Pages\ViewG005M016ItemReservationDetail::route('/{record}'),
        ];
    }
}
