<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDataActions;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G008M017Vehicle;
use App\Models\RecurringAssetSchedule;
use App\Services\RecurringAssetScheduleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecurringAssetScheduleResource extends Resource
{
    protected static ?string $model = RecurringAssetSchedule::class;

    protected static ?string $navigationGroup = 'Data Aset';

    protected static ?string $navigationLabel = 'Jadwal Rutin Aset';

    protected static ?string $modelLabel = 'Jadwal Rutin Aset';

    protected static ?string $pluralModelLabel = 'Jadwal Rutin Aset';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'jadwal-rutin-aset';

    public static function assetResources(): array
    {
        return [G002M007Item::class => G002M007ItemResource::class,
            G003M006Room::class => G003M006RoomResource::class,
            G008M017Vehicle::class => G008M017VehicleResource::class];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && collect(static::assetResources())->contains(fn ($resource) => $resource::canViewAny());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $resource = static::assetResources()[$record->asset_type] ?? null;
        $asset = $resource ? $resource::getEloquentQuery()->find($record->asset_id) : null;

        return $asset && $resource::canEdit($asset);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(function (Builder $query): void {
            $query->whereRaw('1 = 0');
            foreach (static::assetResources() as $model => $resource) {
                if ($resource::canViewAny()) {
                    $query->orWhere(fn (Builder $part) => $part->where('asset_type', $model)
                        ->whereIn('asset_id', $resource::getEloquentQuery()->select((new $model)->qualifyColumn('id'))));
                }
            }
        });
    }

    public static function scheduleFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label('Nama kegiatan rutin')->required()->maxLength(255),
            Forms\Components\CheckboxList::make('weekdays')->label('Berulang setiap')
                ->options([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'])
                ->required()->columns(4),
            Forms\Components\TimePicker::make('start_time')->label('Mulai')->seconds(false)->required(),
            Forms\Components\TimePicker::make('end_time')->label('Selesai')->seconds(false)->required()
                ->helperText('Jam selesai sebelum/sama dengan mulai berarti berakhir esok hari. Jam sama memblokir 24 jam. Zona waktu: '.config('app.timezone').'.'),
            Forms\Components\DatePicker::make('valid_from')->label('Berlaku mulai')->required()->default(today()),
            Forms\Components\DatePicker::make('valid_until')->label('Berlaku sampai')->afterOrEqual('valid_from')
                ->helperText('Kosong berarti berulang tanpa batas tanggal. Tanggal berlaku mengikuti hari mulai kegiatan.'),
            Forms\Components\Toggle::make('is_active')->label('Aktif memblokir booking')->default(true),
            Forms\Components\Placeholder::make('booking_notice')->label('Ketentuan')
                ->content('Semua jumlah barang, ruangan, atau kendaraan ini tidak dapat dipesan pada jam rutin. Jadwal yang berbenturan dengan booking aktif ditolak; ubah booking terlebih dahulu.'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('asset_type')->label('Jenis aset')
                ->options(array_combine(array_values(RecurringAssetScheduleService::assetModels()), ['Barang', 'Ruangan', 'Kendaraan']))
                ->required()->live()->disabledOn('edit')
                ->afterStateUpdated(fn (Forms\Set $set) => $set('asset_id', null)),
            Forms\Components\Select::make('asset_id')->label('Aset')->searchable()->required()->disabledOn('edit')
                ->options(function (Forms\Get $get): array {
                    $resource = static::assetResources()[$get('asset_type')] ?? null;
                    if (! $resource || ! $resource::canViewAny()) {
                        return [];
                    }

                    return $resource::getEloquentQuery()->get()->filter(fn ($asset) => $resource::canEdit($asset))->pluck('name', 'id')->all();
                }),
            ...static::scheduleFields(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $table->headerActions(ResourceDataActions::make(static::class));

        return $table->columns([
            Tables\Columns\TextColumn::make('asset.name')->label('Aset'),
            Tables\Columns\TextColumn::make('name')->label('Kegiatan')->searchable(),
            Tables\Columns\TextColumn::make('weekdays')->label('Hari')->formatStateUsing(fn ($state) => [1 => 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][(int) $state] ?? $state)->badge(),
            Tables\Columns\TextColumn::make('start_time')->label('Mulai'),
            Tables\Columns\TextColumn::make('end_time')->label('Selesai'),
            Tables\Columns\TextColumn::make('valid_from')->label('Sejak')->date(),
            Tables\Columns\TextColumn::make('valid_until')->label('Sampai')->date()->placeholder('Tanpa batas'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RecurringAssetScheduleResource\Pages\ManageRecurringAssetSchedules::route('/')];
    }
}
