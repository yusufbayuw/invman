<?php

namespace App\Filament\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class AjukanPeminjaman extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationLabel = 'Ajukan Peminjaman';
    protected static ?string $title = 'Ajukan Peminjaman';
    protected static ?int $navigationSort = -10;
    protected static string $view = 'filament.pages.ajukan-peminjaman';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $start = now()->addHour()->startOfHour();
        $this->form->fill([
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'needs' => [['type' => 'item', 'quantity' => 1]],
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->check()
            && (auth()->user()->isFacility() || auth()->user()->isSarpras());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->isSarpras();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pemohon')
                    ->description('Identitas diambil otomatis dari akun yang sedang digunakan.')
                    ->schema([
                        Placeholder::make('requester')
                            ->label('Diajukan oleh')
                            ->content(auth()->user()?->name ?? '-'),
                        Placeholder::make('unit')
                            ->label('Unit pemohon')
                            ->content(auth()->user()?->unit?->name ?? 'Unit belum diatur'),
                    ])
                    ->columns(2),

                Section::make('Keperluan dan Jadwal')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama kegiatan / keperluan')
                            ->placeholder('Contoh: Rapat koordinasi bulanan')
                            ->maxLength(255)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Deskripsi keperluan')
                            ->placeholder('Jelaskan singkat tujuan dan kebutuhan kegiatan.')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->seconds(false)
                            ->native(false)
                            ->minDate(now())
                            ->live()
                            ->required(),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->seconds(false)
                            ->native(false)
                            ->after('start_time')
                            ->live()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Kebutuhan')
                    ->description('Tambahkan semua barang, ruangan / tempat, dan kendaraan dalam satu pengajuan.')
                    ->schema([
                        Repeater::make('needs')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('type')
                                    ->label('Jenis kebutuhan')
                                    ->options([
                                        'item' => 'Barang / Peralatan',
                                        'room' => 'Ruangan / Tempat',
                                        'vehicle' => 'Kendaraan',
                                    ])
                                    ->live()
                                    ->required(),
                                Select::make('item_id')
                                    ->label('Barang / Peralatan')
                                    ->options(fn () => app(LoanAvailabilityService::class)->itemOptions(
                                        $this->data['start_time'] ?? null,
                                        $this->data['end_time'] ?? null,
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get) => $get('type') === 'item')
                                    ->required(fn (Get $get) => $get('type') === 'item')
                                    ->helperText('Daftar hanya menampilkan barang yang masih tersedia pada jadwal tersebut.'),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->visible(fn (Get $get) => $get('type') === 'item')
                                    ->required(fn (Get $get) => $get('type') === 'item'),
                                Select::make('room_id')
                                    ->label('Ruangan / Tempat')
                                    ->options(fn () => app(LoanAvailabilityService::class)->roomOptions(
                                        $this->data['start_time'] ?? null,
                                        $this->data['end_time'] ?? null,
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get) => $get('type') === 'room')
                                    ->required(fn (Get $get) => $get('type') === 'room')
                                    ->helperText('Aula, lapangan, dan ruang bersama dikelola sebagai Ruangan / Tempat.'),
                                Select::make('vehicle_id')
                                    ->label('Kendaraan')
                                    ->options(fn () => app(LoanAvailabilityService::class)->vehicleOptions(
                                        $this->data['start_time'] ?? null,
                                        $this->data['end_time'] ?? null,
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get) => $get('type') === 'vehicle')
                                    ->required(fn (Get $get) => $get('type') === 'vehicle')
                                    ->helperText('Pengemudi akan ditentukan oleh admin fasilitas setelah pengajuan disetujui.'),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->addActionLabel('Tambah kebutuhan')
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                'item' => 'Barang / Peralatan',
                                'room' => 'Ruangan / Tempat',
                                'vehicle' => 'Kendaraan',
                                default => 'Kebutuhan baru',
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Catatan dan Lampiran')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan tambahan')
                            ->rows(3)
                            ->maxLength(2000),
                        FileUpload::make('attachment')
                            ->label('Lampiran')
                            ->directory('loan-request-attachments')
                            ->maxSize(5120)
                            ->helperText('Opsional, maksimal 5 MB.'),
                    ])
                    ->columns(2),

                Section::make('Ringkasan')
                    ->schema([
                        Placeholder::make('summary')
                            ->hiddenLabel()
                            ->content(function (Get $get): HtmlString {
                                $count = count($get('needs') ?? []);
                                $start = $get('start_time') ?: '-';
                                $end = $get('end_time') ?: '-';

                                return new HtmlString(
                                    '<div class="space-y-1 text-sm">'
                                    . '<p><strong>' . e(auth()->user()?->name) . '</strong> · ' . e(auth()->user()?->unit?->name ?? 'Unit belum diatur') . '</p>'
                                    . '<p>' . e($count) . ' kebutuhan · ' . e($start) . ' sampai ' . e($end) . '</p>'
                                    . '<p class="text-gray-500">Setelah dikirim, status awal pengajuan adalah Menunggu Persetujuan.</p>'
                                    . '</div>'
                                );
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $activity = app(LoanRequestService::class)->submit(auth()->user(), $this->form->getState());

        Notification::make()
            ->title('Pengajuan berhasil dikirim')
            ->body('Pengajuan masuk ke antrean admin fasilitas.')
            ->success()
            ->send();

        $this->redirect(G004M008ActivityResource::getUrl('view', ['record' => $activity]));
    }
}
