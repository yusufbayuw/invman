<?php

namespace App\Filament\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use App\Services\LoanActivityGrouping;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use App\Services\LoanSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class PeminjamanCepat extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Peminjaman';

    protected static ?string $navigationLabel = 'Peminjaman Cepat';

    protected static ?string $title = 'Peminjaman Cepat';

    protected static ?int $navigationSort = -20;

    protected static string $view = 'filament.pages.peminjaman-cepat';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AjukanPeminjaman::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->isSarpras();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $requestedType = request()->query('type', 'item');
        $type = is_string($requestedType) && in_array($requestedType, ['item', 'room', 'vehicle'], true)
            ? $requestedType
            : 'item';

        $start = now()->addHour()->startOfHour();

        $this->form->fill([
            'type' => $type,
            'quantity' => 1,
            'activity_mode' => 'new',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('1. Pilih yang akan dipinjam')
                    ->description('Barang, ruangan, atau kendaraan dalam satu pengajuan sederhana.')
                    ->schema([
                        Select::make('type')
                            ->label('Jenis kebutuhan')
                            ->options([
                                'item' => 'Barang / Peralatan',
                                'room' => 'Ruangan / Tempat',
                                'vehicle' => 'Kendaraan',
                            ])
                            ->native(false)
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('asset_id', null);
                                $set('quantity', 1);
                            }),
                        Select::make('asset_id')
                            ->label(fn (Get $get): string => match ($get('type')) {
                                'item' => 'Pilih barang',
                                'room' => 'Pilih ruangan',
                                'vehicle' => 'Pilih kendaraan',
                                default => 'Pilih aset',
                            })
                            ->options(fn (Get $get): array => $this->availableAssets($get('type')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Hanya aset tersedia pada jadwal yang dipilih yang ditampilkan.'),
                        TextInput::make('quantity')
                            ->label('Jumlah barang')
                            ->numeric()
                            ->rules(['integer'])
                            ->minValue(1)
                            ->default(1)
                            ->required(fn (Get $get): bool => $get('type') === 'item')
                            ->visible(fn (Get $get): bool => $get('type') === 'item')
                            ->helperText(fn (Get $get): string => filled($get('asset_id'))
                                ? 'Stok tersedia: '.app(LoanAvailabilityService::class)->availableItemQuantity(
                                    $get('asset_id'),
                                    $get('start_time'),
                                    $get('end_time'),
                                )
                                : 'Jumlah otomatis 1.'),
                    ])
                    ->columns(2),

                Section::make('2. Tentukan waktu')
                    ->schema([
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->before('end_time')
                            ->live()
                            ->required(),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->native(false)
                            ->seconds(false)
                            ->after('start_time')
                            ->live()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('3. Kegiatan dan alasan peminjaman')
                    ->description('Gunakan satu kegiatan yang sama untuk menelusuri beberapa pengajuan terpisah.')
                    ->schema([
                        Select::make('activity_mode')
                            ->label('Kegiatan')
                            ->options([
                                'new' => 'Buat kegiatan baru',
                                'existing' => 'Pilih kegiatan yang sudah ada',
                            ])
                            ->native(false)
                            ->default('new')
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('purpose', null);
                                $set('existing_activity_id', null);
                            }),
                        Select::make('existing_activity_id')
                            ->label('Kegiatan yang sudah ada (unit Anda)')
                            ->options(fn (): array => app(LoanActivityGrouping::class)->options(auth()->user()))
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('activity_mode') === 'existing')
                            ->visible(fn (Get $get): bool => $get('activity_mode') === 'existing')
                            ->helperText('Hanya kegiatan induk dari unit Anda. Setiap pengajuan tetap memiliki jadwal, persetujuan dan histori terpisah.'),
                        TextInput::make('purpose')
                            ->label('Nama / alasan kegiatan baru')
                            ->placeholder('Contoh: Rapat guru / antar siswa ke kegiatan')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('activity_mode') !== 'existing')
                            ->visible(fn (Get $get): bool => $get('activity_mode') !== 'existing')
                            ->columnSpanFull(),
                        Textarea::make('asset_note')
                            ->label('Catatan khusus peminjaman ini (opsional)')
                            ->placeholder('Misal: Proyektor untuk presentasi materi pembukaan')
                            ->maxLength(2000)
                            ->rows(2)
                            ->visible(fn (Get $get): bool => $get('activity_mode') === 'existing')
                            ->columnSpanFull(),
                        Placeholder::make('requester')
                            ->label('Pemohon')
                            ->content(fn (): string => auth()->user()?->name ?? '-'),
                        Placeholder::make('unit')
                            ->label('Unit')
                            ->content(fn (): string => auth()->user()?->unit?->name ?? 'Belum diatur'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /** @return array<int|string, string> */
    private function availableAssets(mixed $type): array
    {
        $start = $this->data['start_time'] ?? null;
        $end = $this->data['end_time'] ?? null;
        $availability = app(LoanAvailabilityService::class);

        return match ($type) {
            'item' => $availability->itemOptions($start, $end),
            'room' => $availability->roomOptions($start, $end),
            'vehicle' => $availability->vehicleOptions($start, $end),
            default => [],
        };
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $type = $data['type'] ?? null;
        $assetId = $data['asset_id'] ?? null;

        // Never forward raw Livewire/form state to models or the loan service.
        if (! in_array($type, ['item', 'room', 'vehicle'], true) || ! ctype_digit((string) $assetId) || (int) $assetId < 1) {
            throw ValidationException::withMessages(['data.asset_id' => 'Pilih aset yang valid.']);
        }

        $need = match ($type) {
            'item' => [
                'type' => 'item',
                'item_id' => (int) $assetId,
                'quantity' => (int) ($data['quantity'] ?? 1),
            ],
            'room' => ['type' => 'room', 'room_id' => (int) $assetId],
            'vehicle' => ['type' => 'vehicle', 'vehicle_id' => (int) $assetId],
        };

        $mode = $data['activity_mode'] ?? 'new';

        if (! in_array($mode, ['new', 'existing'], true)) {
            throw ValidationException::withMessages(['data.activity_mode' => 'Pilihan kegiatan tidak valid.']);
        }

        $root = $mode === 'existing'
            ? app(LoanActivityGrouping::class)->resolve(auth()->user(), $data['existing_activity_id'] ?? null)
            : null;
        $purpose = $root?->name ?? trim((string) ($data['purpose'] ?? ''));

        if ($purpose === '') {
            throw ValidationException::withMessages(['data.purpose' => 'Alasan peminjaman wajib diisi.']);
        }

        $payload = [
            'name' => $purpose,
            'description' => $root?->description ?: $purpose,
            'notes' => $root ? ($data['asset_note'] ?? null) : null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'needs' => [$need],
        ];

        if ($root) {
            $payload['related_activity_id'] = $root->id;
        }

        $activity = app(LoanRequestService::class)->submit(auth()->user(), $payload);

        Notification::make()
            ->title('Peminjaman berhasil diajukan')
            ->body('Pengajuan menunggu persetujuan pengelola. Aset ditahan sementara hingga '
                .app(LoanSettings::class)->holdHours().' jam.')
            ->success()
            ->send();

        $this->redirect(G004M008ActivityResource::getUrl('view', ['record' => $activity]));
    }
}
