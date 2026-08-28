<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case CheckedOut = 'checked_out';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::PartiallyApproved => 'Disetujui Sebagian',
            self::Rejected => 'Ditolak',
            self::CheckedOut => 'Sedang Dipakai',
            self::ReturnRequested => 'Menunggu Konfirmasi Pengembalian',
            self::Returned => 'Selesai / Dikembalikan',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::PartiallyApproved => 'warning',
            self::Rejected => 'danger',
            self::CheckedOut => 'info',
            self::ReturnRequested => 'warning',
            self::Returned => 'primary',
            self::Cancelled => 'gray',
            self::Expired => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }

    public static function nonBlockingValues(): array
    {
        return [
            self::Rejected->value,
            self::Returned->value,
            self::Cancelled->value,
            self::Draft->value,
            self::Expired->value,
        ];
    }

    public static function blockingValues(): array
    {
        return [
            self::Submitted->value,
            self::Approved->value,
            self::PartiallyApproved->value,
            self::CheckedOut->value,
            self::ReturnRequested->value,
        ];
    }

    public static function confirmedBlockingValues(): array
    {
        return [
            self::Approved->value,
            self::PartiallyApproved->value,
            self::CheckedOut->value,
            self::ReturnRequested->value,
        ];
    }

    public function blocksAvailability(): bool
    {
        return in_array($this->value, self::blockingValues(), true);
    }
}
