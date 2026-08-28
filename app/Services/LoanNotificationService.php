<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G004M008Activity;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

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
            Notification::make()
                ->title('Pengajuan berhasil dikirim')
                ->body("{$activity->name} telah masuk ke antrean. Aset ditahan hingga " . ($activity->hold_expires_at?->translatedFormat('d M Y, H:i') ?? '-') . '.')
                ->success()
                ->icon('heroicon-o-paper-airplane')
                ->actions([$this->viewAction($activity, 'Lihat pengajuan')])
                ->sendToDatabase($activity->user);
        }

        $reviewers = $this->reviewers(except: $activity->user);

        if ($reviewers->isEmpty()) {
            return;
        }

        $unit = $activity->unit?->name ?? 'Tanpa unit';
        $schedule = $activity->start_time?->translatedFormat('d M Y, H:i') ?? '-';
        $deadline = $activity->hold_expires_at?->translatedFormat('d M Y, H:i') ?? '-';

        Notification::make()
            ->title('Pengajuan baru perlu ditinjau')
            ->body("{$activity->user?->name} dari {$unit} mengajukan {$activity->name} untuk {$schedule}. Putuskan sebelum {$deadline}.")
            ->warning()
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([$this->viewAction($activity, 'Tinjau sekarang', true)])
            ->sendToDatabase($reviewers);
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

        if ($to !== ReservationStatus::Cancelled) {
            return;
        }

        $reviewers = $this->reviewers(except: $activity->user);

        if ($reviewers->isNotEmpty()) {
            Notification::make()
                ->title('Pengajuan dibatalkan pemohon')
                ->body("{$activity->name} tidak lagi memerlukan tindakan persetujuan.")
                ->warning()
                ->icon('heroicon-o-x-circle')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($reviewers);
        }
    }

    public function holdExpired(
        G004M008Activity $activity,
        ReservationStatus $from,
        ReservationStatus $to,
    ): void {
        $activity->loadMissing('user');

        if ($activity->user) {
            Notification::make()
                ->title($to === ReservationStatus::PartiallyApproved
                    ? 'Sebagian hold pengajuan berakhir'
                    : 'Masa tahan pengajuan berakhir')
                ->body($to === ReservationStatus::PartiallyApproved
                    ? "Kebutuhan {$activity->name} yang belum diputuskan telah dilepaskan. Kebutuhan yang disetujui tetap tercatat."
                    : "Pengajuan {$activity->name} tidak diproses dalam batas waktu dan aset telah tersedia kembali.")
                ->warning()
                ->icon('heroicon-o-clock')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($activity->user);
        }

        $reviewers = $this->reviewers(except: $activity->user);

        if ($reviewers->isNotEmpty()) {
            Notification::make()
                ->title('Hold peminjaman kedaluwarsa')
                ->body("Kebutuhan yang masih menunggu pada {$activity->name} telah dilepaskan otomatis.")
                ->warning()
                ->icon('heroicon-o-clock')
                ->actions([$this->viewAction($activity, 'Lihat detail', true)])
                ->sendToDatabase($reviewers);
        }
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

    /** @return Collection<int, User> */
    private function reviewers(?User $except = null): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                config('role.admin'),
                config('role.fasilitas'),
            ]))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
