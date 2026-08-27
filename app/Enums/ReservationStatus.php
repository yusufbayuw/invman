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
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::PartiallyApproved => 'Disetujui Sebagian',
            self::Rejected => 'Ditolak',
            self::CheckedOut => 'Sedang Dipakai',
            self::Returned => 'Selesai / Dikembalikan',
            self::Cancelled => 'Dibatalkan',
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
            self::Returned => 'primary',
            self::Cancelled => 'gray',
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
        ];
    }
}
