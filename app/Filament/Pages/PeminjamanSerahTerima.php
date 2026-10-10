<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Services\LoanHandoverQrService;
use App\Services\LoanRequestService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class PeminjamanSerahTerima extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Peminjaman';

    protected static ?string $navigationLabel = 'Serah Terima QR';

    protected static ?string $title = 'Serah Terima dan Pengembalian';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.peminjaman-serah-terima';

    public string $type = '';

    public string $reservationId = '';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isFacility() || $user->isSarpras() || $user->isAssetManager()));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->type = is_string(request()->query('type')) ? request()->query('type') : '';
        $this->reservationId = is_string(request()->query('reservation')) ? request()->query('reservation') : '';

        $reservation = $this->reservation();
        $this->form->fill($this->initialChecklist($reservation));
    }

    private function reservation(): Model
    {
        $qr = app(LoanHandoverQrService::class);
        $reservation = $qr->resolve($this->type, $this->reservationId);

        abort_unless($qr->canView(auth()->user(), $reservation), 404);

        return $reservation;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kondisi saat pengembalian')
                ->description('Periksa kondisi fisik; informasi ini tersimpan dalam histori serah-terima.')
                ->schema($this->returnFields())
                ->visible(fn (): bool => $this->canBeginReturn())
                ->columns(2),
        ])->statePath('data');
    }

    /** @return array<int, Forms\Components\Component> */
    private function returnFields(): array
    {
        $common = [
            Forms\Components\FileUpload::make('proof_path')
                ->label('Bukti serah-terima (opsional)')
                ->directory('loan-return-receipts')
                ->maxSize(5120),
            Forms\Components\Textarea::make('receipt_notes')
                ->label('Catatan serah-terima')
                ->columnSpanFull(),
        ];

        if ($this->type === 'item') {
            return [
                Forms\Components\Repeater::make('instances')
                    ->label('Kondisi setiap barang satuan')
                    ->schema([
                        Forms\Components\Hidden::make('item_instance_id'),
                        Forms\Components\TextInput::make('instance_label')
                            ->label('Kode / nama')->disabled()->dehydrated(false),
                        Forms\Components\Toggle::make('is_ok')
                            ->label('Kondisi baik')->default(true)->live(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan kondisi')
                            ->required(fn (Forms\Get $get): bool => ! $get('is_ok')),
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto kondisi')->image()
                            ->directory('loan-return-checklists')->maxSize(5120),
                    ])
                    ->addable(false)->deletable(false)->reorderable(false)
                    ->columns(2)->columnSpanFull(),
                ...$common,
            ];
        }

        return [
            Forms\Components\Toggle::make('is_ok')
                ->label('Aset dalam kondisi baik')->default(true)->live(),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan kondisi')
                ->required(fn (Forms\Get $get): bool => ! $get('is_ok')),
            Forms\Components\FileUpload::make('photo')
                ->label('Foto kondisi')->image()
                ->directory('loan-return-checklists')->maxSize(5120),
            ...$common,
        ];
    }

    /** @return array<string, mixed> */
    private function initialChecklist(Model $reservation): array
    {
        if (! $reservation instanceof G005M009ItemReservation) {
            return ['is_ok' => true];
        }

        return ['instances' => $reservation->item_reservation_detail()
            ->with('item_instance')->get()->map(fn ($detail): array => [
                'item_instance_id' => $detail->g002_m015_item_instance_id,
                'instance_label' => $detail->item_instance?->code ?: ($detail->item_instance?->name ?? '#'.$detail->g002_m015_item_instance_id),
                'is_ok' => true,
            ])->all()];
    }

    private function canBeginReturn(): bool
    {
        $reservation = $this->reservation();
        $service = app(LoanRequestService::class);

        return $service->canRecordManagedReturn($reservation) || $service->canRequestReservationReturn($reservation);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkout')
                ->label('Pinjamkan')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->visible(fn (): bool => app(LoanRequestService::class)
                    ->canCheckoutReservation($this->reservation()))
                ->requiresConfirmation()
                ->modalDescription('Pastikan aset dan penerima sudah sesuai sebelum mencatat penyerahan.')
                ->action(function (): void {
                    $reservation = $this->reservation();
                    app(LoanRequestService::class)->processReservation(
                        $this->type,
                        (string) $reservation->getKey(),
                        ReservationStatus::CheckedOut,
                    );
                    Notification::make()->title('Aset telah diserahkan')->success()->send();
                    $this->form->fill($this->initialChecklist($this->reservation()));
                }),
            Actions\Action::make('confirmReturn')
                ->label('Konfirmasi Pengembalian')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->visible(fn (): bool => app(LoanRequestService::class)
                    ->canConfirmReturn($this->reservation()))
                ->requiresConfirmation()
                ->modalDescription('Pastikan kondisi fisik dan bukti pengembalian telah diperiksa. Konfirmasi memerlukan pihak kedua.')
                ->action(function (): void {
                    $reservation = $this->reservation();
                    app(LoanRequestService::class)->confirmReturn($this->type, (string) $reservation->getKey());
                    Notification::make()->title('Pengembalian berhasil dikonfirmasi')->success()->send();
                }),
        ];
    }

    public function submitReturn(): void
    {
        $reservation = $this->reservation();
        $service = app(LoanRequestService::class);

        if ($service->canRecordManagedReturn($reservation)) {
            $service->completeManagedReturn($this->type, (string) $reservation->getKey(), $this->form->getState());
        } elseif ($service->canRequestReservationReturn($reservation)) {
            $service->requestReservationReturn($this->type, (string) $reservation->getKey(), $this->form->getState());
        } else {
            abort(403);
        }

        Notification::make()
            ->title('Pengembalian dicatat; menunggu konfirmasi pihak kedua')
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $reservation = $this->reservation();
        $qr = app(LoanHandoverQrService::class);

        return [
            'record' => $reservation,
            'assetName' => $qr->assetName($this->type, $reservation),
            'qrImage' => $qr->pngDataUri($this->type, $reservation),
            'scanUrl' => $qr->signedScanUrl($this->type, $reservation),
            'canBeginReturn' => $this->canBeginReturn(),
            'statusLabel' => ReservationStatus::tryFrom($reservation->status)?->label() ?? $reservation->status,
        ];
    }
}
