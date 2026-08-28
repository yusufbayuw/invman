<?php

namespace App\Filament\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use App\Services\LoanSettings;
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
use Filament\Forms\Set;
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

    public ?string $draftId = null;

    public function mount(?string $record = null): void
    {
        abort_unless(static::canAccess(), 403);

        if ($record) {
            $this->draftId = $record;
            $draft = G004M008Activity::query()
                ->with(['item_reservation', 'room_reservation', 'vehicle_reservation'])
                ->findOrFail($record);

            abort_unless(
                auth()->user()?->belongsToUnit($draft->g001_m001_unit_id)
                && $draft->status === \App\Enums\ReservationStatus::Draft->value,
                403,
            );

            $this->form->fill([
                'name' => $draft->name,
                'description' => $draft->description,
                'notes' => $draft->notes,
                'attachment' => $draft->attachment,
                'start_time' => $draft->start_time,
                'end_time' => $draft->end_time,
                'needs' => $this->draftNeeds($draft),
            ]);

            return;
        }

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

    public function refreshAvailability(): void
    {
        // Livewire polling merender ulang pilihan dan batas stok dari database.
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
                            ->label('Deskripsi kegiatan')
                            ->placeholder('Jelaskan singkat tujuan dan rangkaian kegiatan.')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->seconds(false)
                            ->native(false)
                            ->minDate(now())
                            ->maxDate(fn (Get $get) => $get('end_time'))
                            ->before('end_time')
                            ->validationMessages([
                                'before' => 'Waktu mulai harus sebelum waktu selesai.',
                            ])
                            ->helperText('Tidak dapat melewati waktu selesai.')
                            ->live()
                            ->required(),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->seconds(false)
                            ->native(false)
                            ->minDate(fn (Get $get) => $get('start_time'))
                            ->after('start_time')
                            ->validationMessages([
                                'after' => 'Waktu selesai harus setelah waktu mulai.',
                            ])
                            ->helperText('Tidak dapat mendahului waktu mulai.')
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
                                    ->options(fn (Get $get) => $this->availableOptions('item', 'item_id', $get('item_id')))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('quantity', 1))
                                    ->visible(fn (Get $get) => $get('type') === 'item')
                                    ->required(fn (Get $get) => $get('type') === 'item')
                                    ->helperText('Barang yang sudah dipilih di baris lain disembunyikan.'),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): ?int => filled($get('item_id'))
                                        ? $this->itemAvailableQuantity($get('item_id'))
                                        : null)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->visible(fn (Get $get) => $get('type') === 'item')
                                    ->required(fn (Get $get) => $get('type') === 'item')
                                    ->helperText(fn (Get $get): string => filled($get('item_id'))
                                        ? $this->itemAvailableQuantity($get('item_id')) . ' unit tersedia pada jadwal ini.'
                                        : 'Pilih barang untuk melihat stok tersedia.'),
                                Select::make('room_id')
                                    ->label('Ruangan / Tempat')
                                    ->options(fn (Get $get) => $this->availableOptions('room', 'room_id', $get('room_id')))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get) => $get('type') === 'room')
                                    ->required(fn (Get $get) => $get('type') === 'room')
                                    ->helperText('Aula, lapangan, dan ruang bersama dikelola sebagai Ruangan / Tempat.'),
                                Select::make('vehicle_id')
                                    ->label('Kendaraan')
                                    ->options(fn (Get $get) => $this->availableOptions('vehicle', 'vehicle_id', $get('vehicle_id')))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get) => $get('type') === 'vehicle')
                                    ->required(fn (Get $get) => $get('type') === 'vehicle')
                                    ->helperText('Pengemudi akan ditentukan oleh pengelola kendaraan setelah pengajuan disetujui.'),
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
                                    .'<p><strong>'.e(auth()->user()?->name).'</strong> · '.e(auth()->user()?->unit?->name ?? 'Unit belum diatur').'</p>'
                                    .'<p>'.e($count).' kebutuhan · '.e($start).' sampai '.e($end).'</p>'
                                    .'<p class="text-gray-500">Setelah dikirim, status awal pengajuan adalah Menunggu Persetujuan.</p>'
                                    .'</div>'
                                );
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    private function availableOptions(string $type, string $field, mixed $currentId): array
    {
        $availability = app(LoanAvailabilityService::class);
        $options = match ($type) {
            'item' => $availability->itemOptions($this->data['start_time'] ?? null, $this->data['end_time'] ?? null),
            'room' => $availability->roomOptions($this->data['start_time'] ?? null, $this->data['end_time'] ?? null),
            'vehicle' => $availability->vehicleOptions($this->data['start_time'] ?? null, $this->data['end_time'] ?? null),
        };
        $selectedElsewhere = collect($this->data['needs'] ?? [])
            ->where('type', $type)
            ->pluck($field)
            ->filter(fn ($id) => (string) $id !== (string) $currentId)
            ->map(fn ($id) => (string) $id)
            ->all();

        return collect($options)
            ->reject(fn ($label, $id) => in_array((string) $id, $selectedElsewhere, true))
            ->all();
    }

    private function itemAvailableQuantity(mixed $itemId): int
    {
        return app(LoanAvailabilityService::class)->availableItemQuantity(
            $itemId,
            $this->data['start_time'] ?? null,
            $this->data['end_time'] ?? null,
        );
    }

    public function submit(): void
    {
        $draft = $this->currentDraft();
        $activity = $draft
            ? app(LoanRequestService::class)->submitDraft(auth()->user(), app(LoanRequestService::class)->saveDraft(auth()->user(), $this->form->getState(), $draft))
            : app(LoanRequestService::class)->submit(auth()->user(), $this->form->getState());

        Notification::make()
            ->title('Pengajuan berhasil dikirim')
            ->body('Aset ditahan sementara selama ' . app(LoanSettings::class)->holdHours() . ' jam sambil menunggu keputusan pengelola aset.')
            ->success()
            ->icon('heroicon-o-paper-airplane')
            ->seconds(8)
            ->send();

        $this->redirect(G004M008ActivityResource::getUrl('view', ['record' => $activity]));
    }

    public function saveDraft(): void
    {
        $activity = app(LoanRequestService::class)->saveDraft(auth()->user(), $this->form->getState(), $this->currentDraft());

        Notification::make()
            ->title('Draf disimpan')
            ->body('Anda masih dapat mengubah draf ini sebelum mengajukannya.')
            ->success()
            ->send();

        $this->redirect(static::getUrl(['record' => $activity->id]));
    }

    private function currentDraft(): ?G004M008Activity
    {
        return $this->draftId ? G004M008Activity::query()->find($this->draftId) : null;
    }

    private function draftNeeds(G004M008Activity $draft): array
    {
        return $draft->item_reservation->map(fn ($reservation) => [
            'type' => 'item', 'item_id' => $reservation->g002_m007_item_id, 'quantity' => $reservation->quantity,
        ])->concat($draft->room_reservation->map(fn ($reservation) => [
            'type' => 'room', 'room_id' => $reservation->g003_m006_room_id,
        ]))->concat($draft->vehicle_reservation->map(fn ($reservation) => [
            'type' => 'vehicle', 'vehicle_id' => $reservation->g008_m017_vehicle_id,
        ]))->values()->all();
    }
}
