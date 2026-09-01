<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use App\Models\User;
use App\Notifications\DevicePushNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class LoanNotificationService
{
    public function sendStatusToast(ReservationStatus $status, string $subject): void
    {
        [$title, $body, $notificationStatus, $icon] = match ($status) {
            ReservationStatus::Approved => ['Kebutuhan disetujui', "{$subject} siap dilanjutkan ke penyerahan.", 'success', 'heroicon-o-check-circle'],
            ReservationStatus::Rejected => ['Kebutuhan ditolak', "Keputusan untuk {$subject} berhasil disimpan.", 'danger', 'heroicon-o-x-circle'],
            ReservationStatus::CheckedOut => ['Penyerahan tercatat', "{$subject} tercatat telah diserahkan kepada pemohon.", 'info', 'heroicon-o-arrow-right-circle'],
            ReservationStatus::Returned => ['Pengembalian tercatat', "{$subject} tercatat telah dikembalikan.", 'success', 'heroicon-o-arrow-uturn-left'],
            default => ['Status diperbarui', "Status {$subject} berhasil diperbarui.", 'info', 'heroicon-o-bell'],
        };

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->seconds(6);

        $notification->{$notificationStatus}()->send();
    }

    public function submitted(G004M008Activity $activity): void
    {
        $activity->loadMissing(['user', 'unit']);

        if ($activity->user) {
            $title = 'Pengajuan berhasil dikirim';
            $body = "{$activity->name} telah masuk ke antrean. Aset ditahan hingga ".($activity->hold_expires_at?->translatedFormat('d M Y, H:i') ?? '-').'.';

            Notification::make()
                ->title($title)
                ->body($body)
                ->success()
                ->icon('heroicon-o-paper-airplane')
                ->actions([$this->viewAction($activity, 'Lihat pengajuan')])
                ->sendToDatabase($activity->user);

            $this->sendDevicePush($activity->user, $title, $body, $activity, 'submitted-requester');
        }

        $reviewers = $this->reviewers($activity, except: $activity->user);

        if ($reviewers->isEmpty()) {
            return;
        }

        $unit = $activity->unit?->name ?? 'Tanpa unit';
        $schedule = $activity->start_time?->translatedFormat('d M Y, H:i') ?? '-';
        $deadline = $activity->hold_expires_at?->translatedFormat('d M Y, H:i') ?? '-';

        $title = 'Pengajuan baru perlu ditinjau';
        $body = "{$activity->user?->name} dari {$unit} mengajukan {$activity->name} untuk {$schedule}. Putuskan sebelum {$deadline}.";

        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([$this->viewAction($activity, 'Tinjau sekarang', true)])
            ->sendToDatabase($reviewers);

        $this->sendDevicePush($reviewers, $title, $body, $activity, 'submitted-reviewer');
    }

    public function statusChanged(
        G004M008Activity $activity,
        ReservationStatus $from,
        ReservationStatus $to,
    ): void {
        if ($from === $to) {
            return;
        }

        $activity->loadMissing('user');

        if (! $activity->user) {
            return;
        }

        [$title, $body, $status, $icon] = $this->statusMessage($activity, $to);

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->actions([$this->viewAction($activity, 'Lihat detail', true)]);

        $notification->{$status}()->sendToDatabase($activity->user);

        $this->sendDevicePush($activity->user, $title, $body, $activity, $to->value);

        if ($to !== ReservationStatus::Cancelled) {
            return;
        }

        $reviewers = $this->reviewers($activity, except: $activity->user);

        if ($reviewers->isNotEmpty()) {
            $reviewerTitle = 'Pengajuan dibatalkan pemohon';
            $reviewerBody = "{$activity->name} tidak lagi memerlukan tindakan persetujuan.";

            Notification::make()
                ->title($reviewerTitle)
                ->body($reviewerBody)
                ->warning()
                ->icon('heroicon-o-x-circle')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($reviewers);

            $this->sendDevicePush($reviewers, $reviewerTitle, $reviewerBody, $activity, 'cancelled-reviewer');
        }
    }

    public function holdExpired(
        G004M008Activity $activity,
        ReservationStatus $from,
        ReservationStatus $to,
    ): void {
        $activity->loadMissing('user');

        if ($activity->user) {
            $title = $to === ReservationStatus::PartiallyApproved
                ? 'Sebagian hold pengajuan berakhir'
                : 'Masa tahan pengajuan berakhir';
            $body = $to === ReservationStatus::PartiallyApproved
                ? "Kebutuhan {$activity->name} yang belum diputuskan telah dilepaskan. Kebutuhan yang disetujui tetap tercatat."
                : "Pengajuan {$activity->name} tidak diproses dalam batas waktu dan aset telah tersedia kembali.";

            Notification::make()
                ->title($title)
                ->body($body)
                ->warning()
                ->icon('heroicon-o-clock')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($activity->user);

            $this->sendDevicePush($activity->user, $title, $body, $activity, 'hold-expired-requester');
        }

        $reviewers = $this->reviewers($activity, except: $activity->user);

        if ($reviewers->isNotEmpty()) {
            $title = 'Hold peminjaman kedaluwarsa';
            $body = "Kebutuhan yang masih menunggu pada {$activity->name} telah dilepaskan otomatis.";

            Notification::make()
                ->title($title)
                ->body($body)
                ->warning()
                ->icon('heroicon-o-clock')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($reviewers);

            $this->sendDevicePush($reviewers, $title, $body, $activity, 'hold-expired-reviewer');
        }
    }

    public function overdue(string $type, Model $reservation): void
    {
        $activity = $reservation->activity;
        if (! $activity) {
            return;
        }

        $subject = match ($type) {
            'item' => $reservation->item?->name ?? 'Barang',
            'room' => $reservation->room?->name ?? 'Ruangan',
            'vehicle' => $reservation->vehicle?->name ?? 'Kendaraan',
            default => 'Aset',
        };
        $deadline = $reservation->end_time?->translatedFormat('d M Y, H:i') ?? '-';
        $recipients = collect([$activity->user])
            ->filter()
            ->concat($this->reviewers($activity, except: $activity->user))
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Peminjaman terlambat dikembalikan';
        $body = "{$subject} untuk {$activity->name} melewati batas pengembalian {$deadline}. Ajukan atau catat pengembalian sesuai kondisi fisik aset.";

        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([$this->viewAction($activity, 'Proses pengembalian', true)])
            ->sendToDatabase($recipients);

        $this->sendDevicePush($recipients, $title, $body, $activity, "overdue-{$type}-{$reservation->getKey()}");
    }

    public function returnConfirmationRequired(G004M008Activity $activity, bool $forBorrower): void
    {
        $activity->loadMissing('user');
        $recipients = $forBorrower
            ? collect([$activity->user])->filter()
            : $this->reviewers($activity, except: $activity->user);

        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Konfirmasi serah-terima pengembalian diperlukan';
        $body = $forBorrower
            ? "Pengelola telah mencatat pengembalian fisik untuk {$activity->name}. Konfirmasikan bahwa serah-terima benar."
            : "Pemohon telah mengajukan pengembalian untuk {$activity->name}. Periksa kondisi aset dan konfirmasikan serah-terima.";

        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->icon('heroicon-o-document-check')
            ->actions([$this->viewAction($activity, 'Konfirmasi sekarang', true)])
            ->sendToDatabase($recipients);

        $audience = $forBorrower ? 'borrower' : 'reviewer';
        $this->sendDevicePush($recipients, $title, $body, $activity, "return-confirmation-{$audience}");
    }

    /** @return array{string, string, string, string} */
    private function statusMessage(G004M008Activity $activity, ReservationStatus $status): array
    {
        $name = $activity->name;

        return match ($status) {
            ReservationStatus::Approved => [
                'Pengajuan disetujui',
                "Seluruh kebutuhan untuk {$name} telah disetujui. Periksa detail sebelum waktu pemakaian.",
                'success',
                'heroicon-o-check-badge',
            ],
            ReservationStatus::PartiallyApproved => [
                'Pengajuan disetujui sebagian',
                "Sebagian kebutuhan untuk {$name} disetujui dan sebagian lainnya tidak tersedia. Lihat detail keputusan.",
                'warning',
                'heroicon-o-adjustments-horizontal',
            ],
            ReservationStatus::Rejected => [
                'Pengajuan tidak dapat disetujui',
                "Seluruh kebutuhan untuk {$name} ditolak. Alasan tersedia pada detail pengajuan.",
                'danger',
                'heroicon-o-x-circle',
            ],
            ReservationStatus::CheckedOut => [
                'Aset telah diserahkan',
                "Proses pemakaian untuk {$name} sudah dimulai. Pastikan aset digunakan sesuai pengajuan.",
                'info',
                'heroicon-o-arrow-right-circle',
            ],
            ReservationStatus::Returned => [
                'Pengembalian selesai',
                "Seluruh aset untuk {$name} telah dikembalikan. Silakan lengkapi checklist dan ulasan.",
                'success',
                'heroicon-o-clipboard-document-check',
            ],
            ReservationStatus::Cancelled => [
                'Pengajuan dibatalkan',
                "Pengajuan {$name} beserta seluruh kebutuhannya telah dibatalkan.",
                'warning',
                'heroicon-o-no-symbol',
            ],
            ReservationStatus::Expired => [
                'Masa tahan pengajuan berakhir',
                "Pengajuan {$name} tidak diproses dalam batas waktu dan aset telah tersedia kembali.",
                'warning',
                'heroicon-o-clock',
            ],
            default => [
                'Status pengajuan diperbarui',
                "Status {$name} berubah dari {$status->label()}.",
                'info',
                'heroicon-o-bell',
            ],
        };
    }

    private function viewAction(
        G004M008Activity $activity,
        string $label,
        bool $button = false,
    ): Action {
        $action = Action::make('view')
            ->label($label)
            ->url(G004M008ActivityResource::getUrl('view', ['record' => $activity]))
            ->markAsRead();

        return $button ? $action->button() : $action;
    }

    /** @param User|Collection<int, User> $recipients */
    private function sendDevicePush(
        User | Collection $recipients,
        string $title,
        string $body,
        G004M008Activity $activity,
        string $tagSuffix,
    ): void {
        $users = $recipients instanceof User ? collect([$recipients]) : $recipients;
        $subscribedUsers = $users->filter(
            fn (User $user): bool => $user->pushSubscriptions()->exists(),
        );

        if ($subscribedUsers->isEmpty()) {
            return;
        }

        NotificationFacade::send($subscribedUsers, new DevicePushNotification(
            title: $title,
            body: $body,
            url: G004M008ActivityResource::getUrl('view', ['record' => $activity]),
            type: 'loan',
            tag: "loan-{$activity->getKey()}-{$tagSuffix}",
        ));
    }

    /** @return Collection<int, User> */
    private function reviewers(G004M008Activity $activity, ?User $except = null): Collection
    {
        $managementIds = collect()
            ->concat($activity->item_reservation()->with('item')->get()->pluck('item.g002_m003_item_management_id'))
            ->concat($activity->room_reservation()->with('room')->get()->pluck('room.g002_m003_item_management_id'))
            ->concat($activity->vehicle_reservation()->with('vehicle')->get()->pluck('vehicle.g002_m003_item_management_id'))
            ->filter()
            ->unique();

        $reviewers = User::query()
            ->whereHas('itemManagements', fn ($query) => $query->whereKey($managementIds))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();

        if ($reviewers->isNotEmpty()) {
            return $reviewers;
        }

        // Emergency fallback for legacy assets that have not been assigned yet.
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', config('role.admin')))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
