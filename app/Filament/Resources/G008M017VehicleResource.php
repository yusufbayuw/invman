<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G008M017VehicleResource\Pages;
use App\Models\G008M017Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G008M017VehicleResource extends Resource
{
    protected static ?string $model = G008M017Vehicle::class;

    protected static ?string $navigationGroup = 'Kendaraan';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $slug = 'vehicle';

    protected static ?string $modelLabel = 'Kendaraan';

    protected static ?string $navigationLabel = 'Kendaraan';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 15;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'license_plate', 'status', 'unit.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Nomor Polisi' => $record->license_plate ?? '-',
            'Unit' => $record->unit?->name ?? '-',
            'Kapasitas' => $record->capacity ? number_format($record->capacity).' orang' : '-',
            'Status' => $record->status ?? '-',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('unit');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kendaraan')
                    ->schema([
                        Forms\Components\Select::make('g001_m001_unit_id')
                            ->label('Unit Pengelola')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('g002_m003_item_management_id')
                            ->label('Pengelola Flow')
                            ->relationship('item_management', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('User pada kelompok ini memproses persetujuan dan serah-terima kendaraan.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Kendaraan')
                            ->placeholder('Contoh: Toyota HiAce')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('license_plate')
                            ->label('Nomor Polisi')
                            ->placeholder('Contoh: D 1234 ABC')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\Select::make('default_driver_id')
                            ->label('Pengemudi Default')
                            ->relationship('defaultDriver', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->user?->name ?? 'Pengemudi #'.$record->id)
                            ->searchable()
                            ->preload()
                            ->helperText('Pengemudi otomatis disarankan setiap kali kendaraan disetujui.'),
                        Forms\Components\Toggle::make('requires_assistant')
                            ->label('Bus: wajib kenek')
                            ->default(false)
                            ->helperText('Penugasan kenek dikelola internal saat persetujuan, tidak ditampilkan ke pemohon.'),
                        Forms\Components\TextInput::make('capacity')
                            ->label('Kapasitas Penumpang')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('orang'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Legalitas dan Ketersediaan')
                    ->schema([
                        Forms\Components\DatePicker::make('stnk_date')
                            ->label('Berlaku STNK Sampai')
                            ->native(false),
                        Forms\Components\DatePicker::make('kir_date')
                            ->label('Berlaku KIR Sampai')
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status Kendaraan')
                            ->options([
                                'tersedia' => 'Tersedia',
                                'digunakan' => 'Sedang Digunakan',
                                'perawatan' => 'Dalam Perawatan',
                                'tidak_aktif' => 'Tidak Aktif',
                            ])
                            ->default('tersedia')
                            ->native(false)
                            ->required(),
                        Forms\Components\Toggle::make('is_borrowable')
                            ->label('Dapat Dipinjam')
                            ->helperText('Aktifkan agar kendaraan tersedia pada formulir pengajuan.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kendaraan')
                    ->description(fn (G008M017Vehicle $record): string => $record->license_plate ?: 'Nomor polisi belum diisi')
                    ->weight('medium')
                    ->searchable(['name', 'license_plate'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit Pengelola')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('item_management.name')
                    ->label('Pengelola Flow')
                    ->badge()
                    ->placeholder('Belum ditetapkan'),
                Tables\Columns\TextColumn::make('defaultDriver.user.name')
                    ->label('Pengemudi Default')
                    ->placeholder('Belum ditentukan'),
                Tables\Columns\IconColumn::make('requires_assistant')
                    ->label('Wajib Kenek')
                    ->boolean(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->suffix(' orang')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stnk_date')
                    ->label('Masa Berlaku STNK')
                    ->date('d M Y')
                    ->color(fn ($state): string => $state && $state->isPast() ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kir_date')
                    ->label('Masa Berlaku KIR')
                    ->date('d M Y')
                    ->color(fn ($state): string => $state && $state->isPast() ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'tersedia' => 'Tersedia',
                        'digunakan' => 'Sedang Digunakan',
                        'perawatan' => 'Dalam Perawatan',
                        'tidak_aktif' => 'Tidak Aktif',
                        default => (string) str($state ?? '-')->replace('_', ' ')->title(),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'tersedia' => 'success',
                        'digunakan' => 'info',
                        'perawatan' => 'warning',
                        'tidak_aktif' => 'gray',
                        default => 'gray',
                    }),
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
                Tables\Filters\SelectFilter::make('g001_m001_unit_id')
                    ->label('Unit Pengelola')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'digunakan' => 'Sedang Digunakan',
                        'perawatan' => 'Dalam Perawatan',
                        'tidak_aktif' => 'Tidak Aktif',
                    ]),
                Tables\Filters\TernaryFilter::make('is_borrowable')
                    ->label('Dapat Dipinjam'),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListG008M017Vehicles::route('/'),
            'create' => Pages\CreateG008M017Vehicle::route('/create'),
            'view' => Pages\ViewG008M017Vehicle::route('/{record}'),
            'edit' => Pages\EditG008M017Vehicle::route('/{record}/edit'),
        ];
    }
}
