<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\LoanHandoverReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LoanHandoverDocumentService
{
    /** Build a fresh read-only snapshot from the authoritative loan records. */
    public function document(string $type, Model $reservation): array
    {
        $assetRelation = match ($type) {
            'item' => 'item',
            'room' => 'room',
            'vehicle' => 'vehicle',
            default => throw new \InvalidArgumentException('Jenis reservasi tidak dikenal.'),
        };

        $reservation->loadMissing([
            'activity.unit', 'activity.user', 'activity.loanEvent',
            $assetRelation, 'outboundReceipt', 'returnReceipt',
            'outboundChecklists.itemInstance', 'returnChecklists.itemInstance',
        ]);

        if ($reservation instanceof G005M019VehicleReservation) {
            $reservation->loadMissing('driver.user');
        }

        if ($reservation instanceof G005M009ItemReservation) {
            $reservation->loadMissing('item_reservation_detail.item_instance');
        }

        $checkout = $reservation->outboundReceipt;
        $return = $reservation->returnReceipt;

        $userIds = collect([$checkout, $return])->filter()
            ->flatMap(fn (LoanHandoverReceipt $receipt): array => [
                $receipt->initiated_by,
                $receipt->manager_confirmed_by,
                $receipt->borrower_confirmed_by,
            ])
            ->merge($reservation->outboundChecklists->pluck('checked_by'))
            ->merge($reservation->returnChecklists->pluck('checked_by'))
            ->filter()->unique()->values();

        $users = User::query()->whereKey($userIds)->pluck('name', 'id');
        $actor = fn ($id): string => $id ? (string) ($users[$id] ?? 'Akun tidak lagi tersedia') : 'Belum dikonfirmasi';

        $asset = $reservation->getRelation($assetRelation);
        $assetDescription = match (true) {
            $reservation instanceof G005M019VehicleReservation => collect([
                $asset?->license_plate ? 'No. polisi: '.$asset->license_plate : null,
                $reservation->driver?->user?->name ? 'Pengemudi: '.$reservation->driver->user->name : null,
            ])->filter()->implode(' | '),
            $reservation instanceof G005M010RoomReservation => collect([
                $asset?->floor?->building?->name,
                $asset?->floor?->name,
            ])->filter()->implode(' / '),
            default => '',
        };

        $checkoutRows = $reservation->outboundChecklists->map(
            fn ($check): array => $this->conditionRow($check, $actor, 'loan-checkout-checklists/'),
        )->all();
        $returnRows = $reservation->returnChecklists->map(
            fn ($check): array => $this->conditionRow($check, $actor, 'loan-return-checklists/'),
        )->all();

        $status = ReservationStatus::tryFrom((string) $reservation->status);
        $hasException = filled($checkout?->fallback_reason);
        $complete = $checkout?->completed_at !== null && $return?->completed_at !== null;

        return [
            'reservation' => $reservation,
            'typeLabel' => ['item' => 'Barang', 'room' => 'Ruangan', 'vehicle' => 'Kendaraan'][$type],
            'assetName' => $asset?->name ?? 'Aset tidak tersedia',
            'assetDescription' => $assetDescription,
            'quantity' => $reservation instanceof G005M009ItemReservation ? (int) $reservation->quantity : 1,
            'unitItems' => $reservation instanceof G005M009ItemReservation
                ? $reservation->item_reservation_detail->map(
                    fn ($detail): string => $detail->item_instance?->code
                        ?: ($detail->item_instance?->name ?? '#'.$detail->g002_m015_item_instance_id),
                )->all()
                : [],
            'statusLabel' => $status?->label() ?? (string) $reservation->status,
            'complete' => $complete,
            'hasException' => $hasException,
            'checkout' => $this->receipt($checkout, $actor, 'loan-checkout-receipts/'),
            'returnEvidence' => $this->receipt($return, $actor, 'loan-return-receipts/'),
            'checkoutRows' => $checkoutRows,
            'returnRows' => $returnRows,
            'generatedAt' => now(),
        ];
    }

    private function receipt(?LoanHandoverReceipt $receipt, callable $actor, string $folder): ?array
    {
        if (! $receipt) {
            return null;
        }

        return [
            'number' => $receipt->receipt_number,
            'initiator' => $actor($receipt->initiated_by),
            'manager' => $actor($receipt->manager_confirmed_by),
            'borrower' => $actor($receipt->borrower_confirmed_by),
            'managerAt' => $receipt->manager_confirmed_at,
            'borrowerAt' => $receipt->borrower_confirmed_at,
            'completedAt' => $receipt->completed_at,
            'notes' => $receipt->notes,
            'fallbackReason' => $receipt->fallback_reason,
            'odometer' => $receipt->checkout_odometer,
            'proofImage' => $this->safeImage($receipt->proof_path, $folder),
            'hasProof' => filled($receipt->proof_path),
        ];
    }

    private function conditionRow(Model $check, callable $actor, string $folder): array
    {
        return [
            'instance' => $check->itemInstance?->code
                ?: ($check->itemInstance?->name ?? 'Aset utama'),
            'good' => (bool) $check->is_ok,
            'notes' => $check->notes,
            'checkedBy' => $actor($check->checked_by),
            'checkedAt' => $check->checked_at,
            'photo' => $this->safeImage($check->photo, $folder),
            'hasPhoto' => filled($check->photo),
        ];
    }

    /**
     * Inline only trusted local JPEG/PNG uploads from loan-specific paths.
     * Never accept remote URLs, SVG, traversal paths or unbounded files.
     */
    private function safeImage(?string $path, string $folder): ?string
    {
        if (! $path || ! str_starts_with($path, $folder)
            || str_contains($path, '..') || str_contains($path, '\\')
            || preg_match('/[^A-Za-z0-9_\/.\-]/', $path)) {
            return null;
        }

        $disk = Storage::disk(config('filament.default_filesystem_disk') ?: config('filesystems.default', 'local'));

        if (! $disk->exists($path) || $disk->size($path) > 1500000) {
            return null;
        }

        $mime = $disk->mimeType($path);

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
