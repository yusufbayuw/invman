<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use App\Services\LoanQuickActionService;
use App\Services\LoanRequestService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class QuickActionsWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.quick-actions-widget';

    protected static ?int $sort = -85;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public int $currentPage = 1;

    public int $perPage = 10;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->isFacility() || $user->isSarpras() || $user->isAssetManager());
    }

    public function previousPage(): void
    {
        $this->currentPage = max(1, $this->currentPage - 1);
    }

    public function nextPage(): void
    {
        $this->currentPage = min($this->queuePageCount(), $this->currentPage + 1);
    }

    /** @return array{label: string, color: string, icon: string} */
    public function actionPresentation(string $action): array
    {
        return match ($action) {
            'approve' => ['label' => 'Setujui', 'color' => 'success', 'icon' => 'heroicon-m-check-circle'],
            'reject' => ['label' => 'Tolak', 'color' => 'danger', 'icon' => 'heroicon-m-x-circle'],
            'checkout' => ['label' => 'Pinjamkan', 'color' => 'info', 'icon' => 'heroicon-m-arrow-right-circle'],
            'recordReturn' => ['label' => 'Catat Pengembalian', 'color' => 'warning', 'icon' => 'heroicon-m-clipboard-document-check'],
            'requestReturn' => ['label' => 'Ajukan Pengembalian', 'color' => 'warning', 'icon' => 'heroicon-m-arrow-uturn-left'],
            'confirmReturn' => ['label' => 'Konfirmasi Serah Terima', 'color' => 'warning', 'icon' => 'heroicon-m-check-badge'],
            default => ['label' => 'Tindak', 'color' => 'gray', 'icon' => 'heroicon-m-bolt'],
        };
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->action(function (array $arguments): void {
                $reservation = $this->authorizedReservation($arguments, 'canDecideReservation');
                app(LoanRequestService::class)->processReservation(
                    $arguments['type'],
                    (string) $reservation->getKey(),
                    ReservationStatus::Approved,
                );
                $this->success('Reservasi disetujui', 'Reservasi siap memasuki proses penyerahan.');
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->modalHeading('Tolak reservasi')
            ->modalSubmitActionLabel('Tolak reservasi')
            ->color('danger')
            ->form([
                Forms\Components\Textarea::make('rejection_reason')
                    ->label('Alasan penolakan')
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (array $arguments, array $data): void {
                $reservation = $this->authorizedReservation($arguments, 'canDecideReservation');
                app(LoanRequestService::class)->processReservation(
                    $arguments['type'],
                    (string) $reservation->getKey(),
                    ReservationStatus::Rejected,
                    $data['rejection_reason'],
                );
                $this->success('Reservasi ditolak', 'Alasan penolakan telah disimpan dan pemohon akan diberi tahu.');
            });
    }

    public function checkoutAction(): Action
    {
        return Action::make('checkout')
            ->action(function (array $arguments): void {
                $reservation = $this->authorizedReservation($arguments, 'canCheckoutReservation');
                app(LoanRequestService::class)->processReservation(
                    $arguments['type'],
                    (string) $reservation->getKey(),
                    ReservationStatus::CheckedOut,
                );
                $this->success('Aset dipinjamkan', 'Serah-terima peminjaman berhasil dicatat.');
            });
    }

    public function recordReturnAction(): Action
    {
        return $this->returnAction(
            name: 'recordReturn',
            heading: 'Catat pengembalian',
            eligibility: 'canRecordManagedReturn',
            callback: fn (string $type, string $id, array $data) => app(LoanRequestService::class)->completeManagedReturn($type, $id, $data),
            successTitle: 'Pengembalian dicatat',
            successBody: 'Serah-terima menunggu konfirmasi pihak pemohon.',
        );
    }

    public function requestReturnAction(): Action
    {
        return $this->returnAction(
            name: 'requestReturn',
            heading: 'Ajukan pengembalian',
            eligibility: 'canRequestReservationReturn',
            callback: fn (string $type, string $id, array $data) => app(LoanRequestService::class)->requestReservationReturn($type, $id, $data),
            successTitle: 'Pengembalian diajukan',
            successBody: 'Checklist tersimpan dan menunggu konfirmasi pengelola aset.',
        );
    }

    public function confirmReturnAction(): Action
    {
        return Action::make('confirmReturn')
            ->modalHeading('Konfirmasi serah-terima pengembalian')
            ->modalDescription('Pastikan aset fisik dan bukti serah-terima sudah sesuai sebelum melanjutkan.')
            ->modalSubmitActionLabel('Konfirmasi')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $reservation = $this->authorizedReservation($arguments, 'canConfirmReturn');
                app(LoanRequestService::class)->confirmReturn($arguments['type'], (string) $reservation->getKey());
                $this->success('Serah-terima dikonfirmasi', 'Reservasi telah menyelesaikan proses pengembalian.');
            });
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $queue = app(LoanQuickActionService::class)->forUser($user);
        $pageCount = max(1, (int) ceil($queue->count() / $this->perPage));
        $page = min($this->currentPage, $pageCount);

        return [
            'queue' => $queue->slice(($page - 1) * $this->perPage, $this->perPage)->values(),
            'queueCount' => $queue->count(),
            'currentQueuePage' => $page,
            'queuePageCount' => $pageCount,
        ];
    }

    private function queuePageCount(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 1;
        }

        return max(1, (int) ceil(app(LoanQuickActionService::class)->forUser($user)->count() / $this->perPage));
    }

    private function returnAction(
        string $name,
        string $heading,
        string $eligibility,
        callable $callback,
        string $successTitle,
        string $successBody,
    ): Action {
        return Action::make($name)
            ->modalHeading($heading)
            ->modalSubmitActionLabel('Simpan dan lanjutkan')
            ->color('warning')
            ->fillForm(fn (array $arguments): array => ($arguments['type'] ?? null) === 'item'
                ? $this->itemReturnData($arguments)
                : ['is_ok' => true])
            ->form(fn (array $arguments): array => ($arguments['type'] ?? null) === 'item'
                ? $this->itemReturnForm()
                : $this->assetReturnForm())
            ->action(function (array $arguments, array $data) use ($callback, $eligibility, $successBody, $successTitle): void {
                $reservation = $this->authorizedReservation($arguments, $eligibility);
                $callback($arguments['type'], (string) $reservation->getKey(), $data);
                $this->success($successTitle, $successBody);
            });
    }

    /** @return array<string, mixed> */
    private function itemReturnData(array $arguments): array
    {
        $record = $this->reservation($arguments);

        if (! $record instanceof G005M009ItemReservation) {
            return [];
        }

        return [
            'instances' => $record->item_reservation_detail()
                ->with('item_instance')
                ->get()
                ->map(fn ($detail): array => [
                    'item_instance_id' => $detail->g002_m015_item_instance_id,
                    'instance_label' => $detail->item_instance?->code ?: ($detail->item_instance?->name ?? '#'.$detail->g002_m015_item_instance_id),
                    'is_ok' => true,
                ])
                ->all(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function itemReturnForm(): array
    {
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
            Forms\Components\FileUpload::make('proof_path')
                ->label('Bukti serah-terima')
                ->directory('loan-return-receipts')
                ->maxSize(5120),
            Forms\Components\Textarea::make('receipt_notes')->label('Catatan serah-terima'),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function assetReturnForm(): array
    {
        return [
            Forms\Components\Toggle::make('is_ok')
                ->label('Aset dalam kondisi baik')
                ->default(true)
                ->live(),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan kondisi')
                ->required(fn (Forms\Get $get): bool => ! $get('is_ok')),
            Forms\Components\FileUpload::make('photo')
                ->label('Foto kondisi aset')
                ->directory('loan-return-checklists')
                ->image()
                ->maxSize(5120),
            Forms\Components\FileUpload::make('proof_path')
                ->label('Bukti serah-terima')
                ->directory('loan-return-receipts')
                ->maxSize(5120),
            Forms\Components\Textarea::make('receipt_notes')->label('Catatan serah-terima'),
        ];
    }

    private function authorizedReservation(array $arguments, string $eligibility): Model
    {
        $reservation = $this->reservation($arguments);
        $user = auth()->user();
        $allowed = $reservation && $user && app(LoanRequestService::class)->{$eligibility}($reservation, $user);

        if (! $allowed) {
            Notification::make()
                ->title('Tindakan tidak lagi tersedia')
                ->body('Data mungkin sudah diproses atau berada di luar kewenangan Anda.')
                ->danger()
                ->send();

            $this->unmountAction();

            throw new Halt;
        }

        return $reservation;
    }

    private function reservation(array $arguments): ?Model
    {
        $model = match ($arguments['type'] ?? null) {
            'item' => G005M009ItemReservation::class,
            'room' => G005M010RoomReservation::class,
            'vehicle' => G005M019VehicleReservation::class,
            default => null,
        };

        if (! $model || blank($arguments['reservation_id'] ?? null)) {
            return null;
        }

        return $model::query()
            ->with(['activity', 'returnReceipt'])
            ->find($arguments['reservation_id']);
    }

    private function success(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->seconds(6)
            ->send();
    }
}
