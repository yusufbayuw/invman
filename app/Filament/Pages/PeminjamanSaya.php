<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\LoanRequestNeed;
use App\Services\LoanRequestService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class PeminjamanSaya extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';

    protected static ?string $navigationGroup = 'Peminjaman';

    protected static ?string $navigationLabel = 'Peminjaman Saya';

    protected static ?string $title = 'Peminjaman Saya';

    protected static string $view = 'filament.pages.peminjaman-saya';

    public string $activeType = 'all';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSarpras() || auth()->user()?->isAdmin() || auth()->user()?->isAssetManager();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isAssetManager() ? 'Peminjaman Dikelola' : 'Peminjaman Saya';
    }

    public function getTitle(): string
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isAssetManager() ? 'Peminjaman Dikelola' : 'Peminjaman Saya';
    }

    public function getSubheading(): ?string
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isAssetManager()
            ? 'Kebutuhan yang berasal dari kelompok Pengelola Barang Anda.'
            : 'Seluruh kebutuhan barang, tempat/ruangan, dan kendaraan untuk unit Anda.';
    }

    public function setActiveType(string $type): void
    {
        if (! in_array($type, ['all', 'item', 'room', 'vehicle'], true)) {
            return;
        }

        $this->activeType = $type;
        $this->resetTable();
    }

    public function getTypeCounts(): array
    {
        $counts = static::baseQuery()
            ->select('type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'all' => $counts->sum(),
            'item' => (int) $counts->get('item', 0),
            'room' => (int) $counts->get('room', 0),
            'vehicle' => (int) $counts->get('vehicle', 0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'item' => 'Barang',
                        'room' => 'Tempat / Ruangan',
                        'vehicle' => 'Kendaraan',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'item' => 'primary',
                        'room' => 'warning',
                        'vehicle' => 'info',
                    }),
                TextColumn::make('need_name')
                    ->label('Kebutuhan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_name')
                    ->label('Unit')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('returned_at')
                    ->label('Dikembalikan')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, LoanRequestNeed $record): string => static::isOverdue($record)
                        ? 'Terlambat'
                        : (ReservationStatus::tryFrom($state)?->label() ?? $state ?? '-'))
                    ->color(fn (?string $state, LoanRequestNeed $record): string => static::isOverdue($record)
                        ? 'danger'
                        : (ReservationStatus::tryFrom($state)?->color() ?? 'gray'))
                    ->sortable(),
                TextColumn::make('receipt_number')
                    ->label('No. Serah Terima')
                    ->placeholder('-')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('correction_count')
                    ->label('Koreksi')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('activity_id')
                    ->label('Kegiatan')
                    ->options(static::activityOptions())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ReservationStatus::options())
                    ->multiple(),
            ])
            ->groups([
                Group::make('activity_name')->label('Kegiatan')->collapsible(),
            ])
            ->defaultGroup('activity_name')
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('edit_draft')
                    ->label('Ubah Draf')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (LoanRequestNeed $record): bool => auth()->user()?->isSarpras()
                        && $record->status === ReservationStatus::Draft->value)
                    ->url(fn (LoanRequestNeed $record): string => AjukanPeminjaman::getUrl(['record' => $record->activity_id])),
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LoanRequestNeed $record): bool => auth()->user()?->isSarpras()
                        && $record->activity_status === ReservationStatus::Submitted->value)
                    ->action(function (LoanRequestNeed $record): void {
                        app(LoanRequestService::class)->cancel(G004M008Activity::query()->findOrFail($record->activity_id));
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LoanRequestNeed $record): bool => static::canManageNeed($record)
                        && $record->status === ReservationStatus::Submitted->value)
                    ->action(fn (LoanRequestNeed $record) => app(LoanRequestService::class)->processReservation(
                        $record->type,
                        $record->reservation_id,
                        ReservationStatus::Approved,
                    )),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LoanRequestNeed $record): bool => static::canManageNeed($record)
                        && $record->status === ReservationStatus::Submitted->value)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(fn (LoanRequestNeed $record, array $data) => app(LoanRequestService::class)->processReservation(
                        $record->type,
                        $record->reservation_id,
                        ReservationStatus::Rejected,
                        $data['rejection_reason'],
                    )),
                Tables\Actions\Action::make('checkout')
                    ->label('Pinjamkan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->visible(fn (LoanRequestNeed $record): bool => static::canManageNeed($record)
                        && $record->status === ReservationStatus::Approved->value)
                    ->action(fn (LoanRequestNeed $record) => app(LoanRequestService::class)->processReservation(
                        $record->type,
                        $record->reservation_id,
                        ReservationStatus::CheckedOut,
                    )),
                Tables\Actions\Action::make('confirm_return')
                    ->label('Konfirmasi Serah Terima')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (LoanRequestNeed $record): bool => static::canConfirmNeed($record))
                    ->requiresConfirmation()
                    ->modalDescription('Konfirmasi ini melengkapi serah-terima dua pihak dan menyelesaikan pengembalian.')
                    ->action(fn (LoanRequestNeed $record) => app(LoanRequestService::class)->confirmReturn(
                        $record->type,
                        $record->reservation_id,
                    )),
                Tables\Actions\Action::make('managed_return')
                    ->label('Catat Pengembalian')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (LoanRequestNeed $record): bool => static::canManageNeed($record)
                        && $record->status === ReservationStatus::CheckedOut->value)
                    ->fillForm(fn (LoanRequestNeed $record): array => static::returnChecklistData($record))
                    ->form(fn (LoanRequestNeed $record): array => static::returnChecklistForm($record))
                    ->action(fn (LoanRequestNeed $record, array $data) => app(LoanRequestService::class)->completeManagedReturn(
                        $record->type,
                        $record->reservation_id,
                        $data,
                    )),
                Tables\Actions\Action::make('correct_status')
                    ->label('Koreksi Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (LoanRequestNeed $record): bool => (auth()->user()?->isAdmin() ?? false)
                        && in_array($record->status, [ReservationStatus::ReturnRequested->value, ReservationStatus::Returned->value], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan koreksi')
                            ->helperText('Status akan dibuka kembali menjadi Sedang Dipakai. Histori sebelumnya tetap disimpan.')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (LoanRequestNeed $record, array $data) => app(LoanRequestService::class)->correctReservationStatus(
                        $record->type,
                        $record->reservation_id,
                        ReservationStatus::CheckedOut->value,
                        $data['reason'],
                    )),
                Tables\Actions\Action::make('view_corrections')
                    ->label('Riwayat Koreksi')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (LoanRequestNeed $record): bool => (int) $record->correction_count > 0)
                    ->modalHeading('Riwayat Koreksi Administratif')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (LoanRequestNeed $record) => view('filament.components.loan-correction-history', [
                        'corrections' => static::reservationForNeed($record)?->corrections()->with('correctedBy')->latest()->get() ?? collect(),
                    ])),
                Tables\Actions\Action::make('view')
                    ->label('Lihat Kegiatan')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoanRequestNeed $record): string => G004M008ActivityResource::getUrl('view', ['record' => $record->activity_id])),
            ])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('Belum ada kebutuhan peminjaman')
            ->emptyStateDescription('Barang, tempat/ruangan, atau kendaraan yang diajukan akan tampil di sini.');
    }

    protected function getTableQuery(): Builder
    {
        return static::baseQuery()
            ->when($this->activeType !== 'all', fn (Builder $query): Builder => $query->where('type', $this->activeType));
    }

    private static function activityOptions(): array
    {
        $activityIds = static::baseQuery()->distinct()->pluck('activity_id');

        return G004M008Activity::query()
            ->whereKey($activityIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function baseQuery(): Builder
    {
        return LoanRequestNeed::query()->fromSub(static::needsUnion(), 'loan_request_needs');
    }

    private static function needsUnion(): QueryBuilder
    {
        $user = auth()->user();
        $unitId = $user?->isSarpras() ? $user->g001_m001_unit_id : null;
        $managementIds = $user?->itemManagements()->pluck('g002_m003_item_management.id') ?? collect();
        $scope = static function (QueryBuilder $query) use ($user, $unitId, $managementIds): void {
            if ($user?->isAdmin()) {
                return;
            }

            $query->where(function (QueryBuilder $query) use ($unitId, $managementIds): void {
                if ($unitId) {
                    $query->orWhere('activity.g001_m001_unit_id', $unitId);
                }
                if ($managementIds->isNotEmpty()) {
                    $query->orWhereIn('need.g002_m003_item_management_id', $managementIds);
                }
                if (! $unitId && $managementIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            });
        };

        $items = DB::table('g005_m009_item_reservations as reservation')
            ->join('g004_m008_activities as activity', 'activity.id', '=', 'reservation.g004_m008_activity_id')
            ->leftJoin('g002_m007_items as need', 'need.id', '=', 'reservation.g002_m007_item_id')
            ->leftJoin('g001_m001_units as unit', 'unit.id', '=', 'activity.g001_m001_unit_id')
            ->tap($scope)
            ->selectRaw("CONCAT('item:', reservation.id) as id, reservation.id as reservation_id, 'item' as type, need.name as need_name, activity.id as activity_id, activity.name as activity_name, activity.status as activity_status, unit.name as unit_name, reservation.start_time, reservation.end_time, reservation.returned_at, reservation.status, reservation.created_at, (SELECT receipt_number FROM loan_handover_receipts WHERE reservation_type = 'item' AND reservation_id = reservation.id AND direction = 'return' ORDER BY created_at DESC LIMIT 1) as receipt_number, (SELECT COUNT(*) FROM loan_reservation_corrections WHERE reservation_type = 'item' AND reservation_id = reservation.id) as correction_count");

        $rooms = DB::table('g005_m010_room_reservations as reservation')
            ->join('g004_m008_activities as activity', 'activity.id', '=', 'reservation.g004_m008_activity_id')
            ->leftJoin('g003_m006_rooms as need', 'need.id', '=', 'reservation.g003_m006_room_id')
            ->leftJoin('g001_m001_units as unit', 'unit.id', '=', 'activity.g001_m001_unit_id')
            ->tap($scope)
            ->selectRaw("CONCAT('room:', reservation.id) as id, reservation.id as reservation_id, 'room' as type, need.name as need_name, activity.id as activity_id, activity.name as activity_name, activity.status as activity_status, unit.name as unit_name, reservation.start_time, reservation.end_time, reservation.returned_at, reservation.status, reservation.created_at, (SELECT receipt_number FROM loan_handover_receipts WHERE reservation_type = 'room' AND reservation_id = reservation.id AND direction = 'return' ORDER BY created_at DESC LIMIT 1) as receipt_number, (SELECT COUNT(*) FROM loan_reservation_corrections WHERE reservation_type = 'room' AND reservation_id = reservation.id) as correction_count");

        $vehicles = DB::table('g005_m019_vehicle_reservations as reservation')
            ->join('g004_m008_activities as activity', 'activity.id', '=', 'reservation.g004_m008_activity_id')
            ->leftJoin('g008_m017_vehicles as need', 'need.id', '=', 'reservation.g008_m017_vehicle_id')
            ->leftJoin('g001_m001_units as unit', 'unit.id', '=', 'activity.g001_m001_unit_id')
            ->tap($scope)
            ->selectRaw("CONCAT('vehicle:', reservation.id) as id, reservation.id as reservation_id, 'vehicle' as type, need.name as need_name, activity.id as activity_id, activity.name as activity_name, activity.status as activity_status, unit.name as unit_name, reservation.start_time, reservation.end_time, reservation.returned_at, reservation.status, reservation.created_at, (SELECT receipt_number FROM loan_handover_receipts WHERE reservation_type = 'vehicle' AND reservation_id = reservation.id AND direction = 'return' ORDER BY created_at DESC LIMIT 1) as receipt_number, (SELECT COUNT(*) FROM loan_reservation_corrections WHERE reservation_type = 'vehicle' AND reservation_id = reservation.id) as correction_count");

        return $items->unionAll($rooms)->unionAll($vehicles);
    }

    private static function canManageNeed(LoanRequestNeed $record): bool
    {
        $reservation = static::reservationForNeed($record);

        return $reservation && (auth()->user()?->managesReservation($reservation) ?? false);
    }

    private static function canConfirmNeed(LoanRequestNeed $record): bool
    {
        $reservation = static::reservationForNeed($record);

        return $reservation && app(LoanRequestService::class)->canConfirmReturn($reservation);
    }

    private static function reservationForNeed(LoanRequestNeed $record): G005M009ItemReservation|G005M010RoomReservation|G005M019VehicleReservation|null
    {
        return match ($record->type) {
            'item' => G005M009ItemReservation::query()->with('item')->find($record->reservation_id),
            'room' => G005M010RoomReservation::query()->with('room')->find($record->reservation_id),
            'vehicle' => G005M019VehicleReservation::query()->with('vehicle')->find($record->reservation_id),
            default => null,
        };
    }

    private static function isOverdue(LoanRequestNeed $record): bool
    {
        return in_array($record->status, [ReservationStatus::CheckedOut->value, ReservationStatus::ReturnRequested->value], true)
            && $record->end_time?->isPast();
    }

    private static function returnChecklistData(LoanRequestNeed $record): array
    {
        if ($record->type !== 'item') {
            return ['is_ok' => true];
        }

        $reservation = G005M009ItemReservation::query()->find($record->reservation_id);

        return [
            'instances' => $reservation?->item_reservation_detail()
                ->with('item_instance')
                ->get()
                ->map(fn ($detail): array => [
                    'item_instance_id' => $detail->g002_m015_item_instance_id,
                    'instance_label' => $detail->item_instance?->code ?: ($detail->item_instance?->name ?? '#'.$detail->g002_m015_item_instance_id),
                    'is_ok' => true,
                ])->all() ?? [],
        ];
    }

    private static function returnChecklistForm(LoanRequestNeed $record): array
    {
        $receiptFields = [
            Forms\Components\FileUpload::make('proof_path')
                ->label('Bukti serah-terima')
                ->directory('loan-return-receipts')
                ->maxSize(5120),
            Forms\Components\Textarea::make('receipt_notes')
                ->label('Catatan serah-terima')
                ->columnSpanFull(),
        ];

        if ($record->type !== 'item') {
            return [
                Forms\Components\Toggle::make('is_ok')->label('Aset dalam kondisi baik')->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan kondisi')
                    ->required(fn (Forms\Get $get): bool => ! $get('is_ok')),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto kondisi aset')
                    ->directory('loan-return-checklists')
                    ->image()
                    ->maxSize(5120),
                ...$receiptFields,
            ];
        }

        return [
            Forms\Components\Repeater::make('instances')
                ->label('Kondisi setiap barang satuan')
                ->schema([
                    Forms\Components\Hidden::make('item_instance_id'),
                    Forms\Components\TextInput::make('instance_label')->label('Kode / nama')->disabled()->dehydrated(false),
                    Forms\Components\Toggle::make('is_ok')->label('Kondisi baik')->default(true)->live(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan kondisi')
                        ->required(fn (Forms\Get $get): bool => ! $get('is_ok')),
                    Forms\Components\FileUpload::make('photo')
                        ->label('Foto kondisi')
                        ->directory('loan-return-checklists')
                        ->image()
                        ->maxSize(5120),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columns(2)
                ->columnSpanFull(),
            ...$receiptFields,
        ];
    }
}
