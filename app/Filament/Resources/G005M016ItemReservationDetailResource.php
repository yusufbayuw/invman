<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\G005M016ItemReservationDetail;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;
use App\Filament\Resources\G005M016ItemReservationDetailResource\RelationManagers;

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
        return Auth::user()->hasRole(['super_admin', config('role.fasilitas')]);
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
                    ])
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
            'index' => Pages\ListG005M016ItemReservationDetails::route('/'),
            'create' => Pages\CreateG005M016ItemReservationDetail::route('/create'),
            'view' => Pages\ViewG005M016ItemReservationDetail::route('/{record}'),
            'edit' => Pages\EditG005M016ItemReservationDetail::route('/{record}/edit'),
        ];
    }
}
