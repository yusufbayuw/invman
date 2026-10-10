<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSerahTerima;
use App\Filament\Resources\G004M008ActivityResource;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoanOperationalPulseService
{
    public const CATEGORIES = [
        'approval' => ['label' => 'Menunggu Persetujuan', 'color' => 'warning', 'icon' => 'heroicon-o-clock'],
        'handover' => ['label' => 'Siap Diserahkan', 'color' => 'info', 'icon' => 'heroicon-o-arrow-right-circle'],
        'acceptance' => ['label' => 'Menunggu Penerimaan', 'color' => 'warning', 'icon' => 'heroicon-o-check-badge'],
        'active' => ['label' => 'Sedang Dipakai', 'color' => 'info', 'icon' => 'heroicon-o-cube'],
        'overdue' => ['label' => 'Terlambat', 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle'],
        'returns' => ['label' => 'Konfirmasi Pengembalian', 'color' => 'warning', 'icon' => 'heroicon-o-arrow-uturn-left'],
    ];

    private const TYPES = [
        'item' => ['model' => G005M009ItemReservation::class, 'asset' => 'item', 'label' => 'Barang'],
        'room' => ['model' => G005M010RoomReservation::class, 'asset' => 'room', 'label' => 'Ruangan'],
        'vehicle' => ['model' => G005M019VehicleReservation::class, 'asset' => 'vehicle', 'label' => 'Kendaraan'],
    ];

    public function __construct(
        private readonly LoanVisibility $visibility,
        private readonly LoanRequestService $loans,
        private readonly LoanCheckoutService $checkout,
    ) {}

    /**
     * Dashboard pulse is real-time: only the authorized facility unit filter applies.
     * Month/status filters for historical charts deliberately do not hide old overdue loans.
     *
     * @return array{counts: array<string, int>, rows: array<int, array<string, mixed>>, total: int}
     */
    public function snapshot(User $user, string $selected = 'all', ?int $unitId = null): array
    {
        $categories = array_keys(self::CATEGORIES);
        $selected = $selected === 'all' || in_array($selected, $categories, true) ? $selected : 'all';

        if (! ($user->isFacility() || $user->isSarpras() || $user->isAssetManager())) {
            return ['counts' => array_fill_keys($categories, 0), 'rows' => [], 'total' => 0];
        }

        $counts = array_fill_keys($categories, 0);
        $rows = [];

        foreach ($categories as $category) {
            foreach (self::TYPES as $type => $config) {
                $query = $this->query($user, $category, $type, $unitId);
                $counts[$category] += (clone $query)->count();

                if ($selected !== 'all' && $category !== $selected) {
                    continue;
                }

                $records = $query->with([
                    'activity.unit', 'activity.user', $config['asset'],
                    'outboundReceipt', 'returnReceipt',
                ])->orderBy('end_time')->limit(12)->get();

                foreach ($records as $record) {
                    $rows[] = $this->row($user, $category, $type, $record, $config);
                }
            }
        }

        $priority = array_flip(['overdue', 'returns', 'acceptance', 'approval', 'handover', 'active']);
        usort($rows, function (array $a, array $b) use ($priority): int {
            return ($priority[$a['category']] <=> $priority[$b['category']])
                ?: ($a['sort_at'] <=> $b['sort_at'])
                ?: strcmp($a['key'], $b['key']);
        });

        // A late return may also be active/awaiting confirmation. Show it once
        // in "Semua Prioritas", using its most urgent category.
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['key']])) {
                continue;
            }
            $seen[$row['key']] = true;
            $unique[] = $row;

            if (count($unique) >= 12) {
                break;
            }
        }

        return [
            'counts' => $counts,
            'rows' => $unique,
            'total' => array_sum($counts),
        ];
    }

    private function query(User $user, string $category, string $type, ?int $unitId): Builder
    {
        $config = self::TYPES[$type];
        /** @var class-string<Model> $model */
        $model = $config['model'];

        $query = $this->visibility->reservations($model::query(), $user, $config['asset']);

        // A non-facility actor may never widen its access by setting this parameter.
        if ($user->isFacility() && $unitId && $unitId > 0) {
            $query->whereHas('activity', fn (Builder $q): Builder => $q
                ->where('g001_m001_unit_id', $unitId));
        }

        return match ($category) {
            'approval' => $query->where('status', ReservationStatus::Submitted->value),
            'handover' => $query->where('status', ReservationStatus::Approved->value)
                ->whereDoesntHave('outboundReceipt'),
            'acceptance' => $query->where('status', ReservationStatus::Approved->value)
                ->whereHas('outboundReceipt', fn (Builder $q): Builder => $q
                    ->whereNotNull('manager_confirmed_at')
                    ->whereNull('completed_at')),
            'active' => $query->where('status', ReservationStatus::CheckedOut->value),
            'overdue' => $query
                ->whereIn('status', [ReservationStatus::CheckedOut->value, ReservationStatus::ReturnRequested->value])
                ->where('end_time', '<', now()),
            'returns' => $query->where('status', ReservationStatus::ReturnRequested->value),
        };
    }

    /** @param array{model: string, asset: string, label: string} $configuration
     *  @return array<string, mixed>
     */
    private function row(User $user, string $category, string $type, Model $reservation, array $configuration): array
    {
        $asset = $reservation->getRelation($configuration['asset']);
        $name = $asset?->name ?? 'Aset tidak tersedia';
        if ($type === 'item' && $reservation->quantity > 1) {
            $name .= ' × '.$reservation->quantity;
        }

        $action = 'Lihat transaksi';
        if ($category === 'approval' && $this->loans->canDecideReservation($reservation, $user)) {
            $action = 'Tinjau persetujuan';
        } elseif ($category === 'handover' && $this->loans->canCheckoutReservation($reservation, $user)) {
            $action = 'Catat penyerahan';
        } elseif ($category === 'acceptance' && $this->checkout->canConfirm($reservation, $user)) {
            $action = 'Konfirmasi penerimaan';
        } elseif ($category === 'returns' && $this->loans->canConfirmReturn($reservation, $user)) {
            $action = 'Konfirmasi pengembalian';
        } elseif (in_array($category, ['active', 'overdue'], true)
            && ($this->loans->canRecordManagedReturn($reservation, $user)
                || $this->loans->canRequestReservationReturn($reservation, $user))) {
            $action = 'Proses pengembalian';
        }

        return [
            'key' => $type.':'.$reservation->getKey(),
            'category' => $category,
            'category_label' => self::CATEGORIES[$category]['label'],
            'color' => self::CATEGORIES[$category]['color'],
            'type_label' => $configuration['label'],
            'asset' => $name,
            'activity' => $reservation->activity?->name ?? '-',
            'unit' => $reservation->activity?->unit?->name ?? '-',
            'due' => $reservation->end_time?->translatedFormat('d M Y H:i') ?? '-',
            'sort_at' => $reservation->end_time?->timestamp ?? PHP_INT_MAX,
            'action' => $action,
            'url' => $category === 'approval'
                ? G004M008ActivityResource::getUrl('view', ['record' => $reservation->g004_m008_activity_id])
                : PeminjamanSerahTerima::getUrl([
                    'type' => $type, 'reservation' => $reservation->getKey(),
                ]),
        ];
    }
}
