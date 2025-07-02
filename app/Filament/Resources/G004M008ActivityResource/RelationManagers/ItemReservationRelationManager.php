<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use App\Models\G002M007Item;
use App\Models\G005M009ItemReservation;
use Filament\Forms;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;

class ItemReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'item_reservation';
    protected static ?string $modelLabel = 'Reservasi Barang';
    protected static ?string $title = 'Reservasi Barang';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('g004_m008_activity_id')
                    ->default($this->ownerRecord->id ?? null),
                Forms\Components\Select::make('g002_m007_item_id')
                    ->relationship('item', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->label('Barang')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->default(1)
                    ->numeric()
                    ->hint(function (Get $get) {
                        $itemId = $get('g002_m007_item_id');
                        static $itemCache = [];
                        static $itemOverlappingCache = [];

                        if (!$itemId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (!isset($itemCache[$itemId])) {
                            $itemCache[$itemId] = G002M007Item::find($itemId);
                            $itemOverlappingCache[$itemId] = G005M009ItemReservation::where('g002_m007_item_id', $itemId)
                                ->where('status', '<>', 'dikembalikan')
                                ->where('start_time', '>=', $get('start_time'))
                                ->where('end_time', '<=', $get('end_time'))
                                ->sum('quantity');
                        }

                        $available = ($itemCache[$itemId]?->available_quantity - $itemOverlappingCache[$itemId] ?? 0) ?? 0;
                        return "Saat ini tersedia: {$available}";
                    })
                    ->maxValue(function (Get $get) {
                        $itemId = $get('g002_m007_item_id');
                        static $itemCache = [];
                        static $itemOverlappingCache = [];

                        if (!$itemId) {
                            return 0;
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (!isset($itemCache[$itemId])) {
                            $itemCache[$itemId] = G002M007Item::find($itemId);
                            $itemOverlappingCache[$itemId] = G005M009ItemReservation::where('g002_m007_item_id', $itemId)
                                ->where('status', '<>', 'dikembalikan')
                                ->where('start_time', '>=', $get('start_time'))
                                ->where('end_time', '<=', $get('end_time'))
                                ->sum('quantity');
                        }

                        $available = ($itemCache[$itemId]?->available_quantity - $itemOverlappingCache[$itemId] ?? 0) ?? 0;

                        return $available;
                    })
                    ->minValue(1)
                    ->label('Jumlah Barang')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Waktu Mulai')
                    ->reactive()
                    ->readOnly()
                    ->default($this->ownerRecord->start_time ?? now()),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('Waktu Selesai')
                    ->reactive()
                    ->minDate(fn (Get $get) => $get('start_time') ?? now())
                    ->maxDate($this->ownerRecord->end_time ?? now()->addHour())
                    ->default($this->ownerRecord->end_time ?? now()->addHour()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Nama Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->label('Waktu Mulai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->label('Waktu Selesai'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->color(fn(string $state): string => match ($state) {
                        'menunggu persetujuan' => 'danger',
                        'disetujui/dipinjamkan' => 'success',
                        'dikembalikan' => 'info',
                        'tersedia' => 'primary',
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('konfirmasi')
                    ->label('Setujui')
                    ->color('success')
                    ->hidden(fn ($record): bool => !($record->status === 'menunggu persetujuan'))
                    ->icon('heroicon-o-check-circle')
                    ->action(function ($record) {
                        $record->status = 'disetujui/dipinjamkan';
                        $record->save();
                    }),
                Tables\Actions\Action::make('ditolak')
                    ->label('Ditolak')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn ($record): bool => !($record->status === 'menunggu persetujuan'))
                    ->icon('heroicon-o-x-circle')
                    ->action(function ($record) {
                        $record->status = 'dikembalikan';
                        $record->save();
                    }),
                Tables\Actions\Action::make('dikembalikan')
                    ->label('Kembalikan')
                    ->color('warning')
                    ->hidden(fn ($record): bool => !($record->status === 'disetujui/dipinjamkan'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->action(function ($record) {
                        $record->status = 'dikembalikan';
                        $record->save();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
