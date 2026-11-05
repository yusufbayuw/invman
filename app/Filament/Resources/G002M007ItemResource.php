<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G002M007ItemResource\Pages;
use App\Filament\Resources\G002M007ItemResource\RelationManagers\ItemInstanceRelationManager;
use App\Filament\Resources\G002M007ItemResource\RelationManagers\ItemReservationRelationManager;
use App\Models\G002M007Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class G002M007ItemResource extends Resource
{
    protected static ?string $model = G002M007Item::class;

    protected static ?string $navigationGroup = 'Data Aset';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $slug = 'item';

    protected static ?string $modelLabel = 'Barang';

    protected static ?string $navigationLabel = 'Barang';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 15;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'status', 'unit.name', 'item_type.name', 'room.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Kode' => $record->code ?? '-',
            'Unit' => $record->unit?->name ?? '-',
            'Jenis' => $record->item_type?->name ?? '-',
            'Tersedia' => number_format($record->available_quantity ?? 0).' dari '.number_format($record->quantity ?? 0),
        ];
    }


    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user?->isFacility()) {
            return $query;
        }
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $unitId = $user->isSarpras() ? $user->g001_m001_unit_id : null;
        $managementIds = $user->hasRole(config('role.pengelola_barang'))
            ? $user->itemManagements()->pluck('g002_m003_item_management.id')
            : collect();

        if ($unitId === null && $managementIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (\Illuminate\Database\Eloquent\Builder $scope) use ($unitId, $managementIds): void {
            if ($unitId !== null) {
                $scope->orWhere('g001_m001_unit_id', $unitId);
            }
            if ($managementIds->isNotEmpty()) {
                $scope->orWhereIn('g002_m003_item_management_id', $managementIds);
            }
        });
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::getEloquentQuery()->with(['unit', 'item_type', 'room']);
    }

    public static function infolist(Infolist $infolist): Infolist
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
                        \Filament\Infolists\Components\TextEntry::make('code')
                            ->label('Kode Barang')
                            ->badge()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('tracking_mode')->label('Metode Pencatatan')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('quantity')
                            ->label('Jumlah Barang')
                            ->inlineLabel()
                            ->numeric(),
                        \Filament\Infolists\Components\IconEntry::make('is_borrowable')
                            ->label('Dapat Dipinjam')
                            ->boolean()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status Barang')
                            ->badge()
                            ->inlineLabel(),
                    ])->grow(false),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('unit.name')
                            ->label('Unit Pemilik')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('item_management.name')
                            ->label('Pengelola Barang')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('item_type.name')
                            ->label('Jenis Barang')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('room.name')
                            ->label('Ruangan Penempatan')
                            ->inlineLabel(),
                    ])->grow(false),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ])->grow(false),
                ])->from('md')->columnSpanFull(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->relationship('unit', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) =>
                        auth()->user()?->isSarpras() && ! auth()->user()?->isFacility()
                            ? $query->whereKey(auth()->user()->g001_m001_unit_id)
                            : $query)
                    ->default(fn () => auth()->user()?->isSarpras() && ! auth()->user()?->isFacility()
                        ? auth()->user()->g001_m001_unit_id : null)
                    ->required(fn (): bool => auth()->user()?->isSarpras() && ! auth()->user()?->isFacility())
                    ->label('Unit Pemilik')
                    ->helperText('Sarpras hanya boleh mencatat barang milik unitnya sendiri.'),
                Forms\Components\Select::make('g002_m003_item_management_id')
                    ->relationship('item_management', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => (auth()->user()?->isFacility() || auth()->user()?->isSarpras()) ? $query : $query->whereIn('id', auth()->user()?->itemManagements()->pluck('g002_m003_item_management.id') ?? []))
                    ->label('Pengelola Barang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Menentukan user yang berwenang memproses flow peminjaman barang ini.'),
                Forms\Components\Select::make('g002_m002_item_type_id')
                    ->relationship('item_type', 'name')
                    ->label('Jenis Barang'),
                Forms\Components\Select::make('g003_m006_room_id')
                    ->relationship('room', 'name', modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                        $user = auth()->user();
                        if ($user?->isSarpras() && ! $user->isFacility()) {
                            $query->where(fn (\Illuminate\Database\Eloquent\Builder $rooms) =>
                                $rooms->where('g001_m001_unit_id', $user->g001_m001_unit_id)
                                    ->orWhereNull('g001_m001_unit_id'));
                        }
                        return $query;
                    })
                    ->label('Ruangan Penempatan')
                    ->helperText('Ruangan Tanpa Unit boleh dipakai sebagai lokasi barang, tanpa mengubah unit pemilik barang.'),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Barang'),
                Forms\Components\TextInput::make('code')
                    ->label('Kode Barang')->unique(ignoreRecord: true),
                Forms\Components\Select::make('tracking_mode')
                    ->label('Metode Pencatatan')
                    ->options(['individual' => 'Individual (nomor inventaris per unit)', 'bulk' => 'Kuantitas / Bulk (tanpa unit satuan)'])
                    ->default('individual')
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Metode pencatatan tidak dapat diganti setelah aset dibuat.'),
                Forms\Components\TextInput::make('quantity')
                    ->label('Jumlah Barang')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Setelah dibuat, jumlah mengikuti data Barang Satuan.'),
                Forms\Components\Toggle::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->inlineLabel(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tracking_mode')->label('Pencatatan')->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah Barang')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Jumlah Tersedia')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Barang')
                    ->badge(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit Pemilik')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_management.name')
                    ->label('Pengelola Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_type.name')
                    ->label('Jenis Barang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan Penempatan')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Actions\Action::make('adjustBulkStock')
                    ->label('Koreksi Stok Bulk')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (G002M007Item $record): bool => $record->isBulk() && static::canEdit($record))
                    ->form([
                        Forms\Components\TextInput::make('delta')->label('Perubahan (+/-)')->integer()->required()->notIn([0]),
                        Forms\Components\Textarea::make('reason')->label('Alasan koreksi stok')->required()->minLength(5),
                    ])
                    ->action(fn (G002M007Item $record, array $data) =>
                        app(\App\Services\AssetBulkStockService::class)->adjust(
                            $record, (int) $data['delta'], $data['reason'], auth()->user(),
                        )),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            ItemInstanceRelationManager::class,
            ItemReservationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG002M007Items::route('/'),
            'create' => Pages\CreateG002M007Item::route('/create'),
            'view' => Pages\ViewG002M007Item::route('/{record}'),
            'edit' => Pages\EditG002M007Item::route('/{record}/edit'),
        ];
    }
}
