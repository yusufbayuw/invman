<?php
namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G004M008Activity;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M018Driver;
use App\Models\User;
use App\Models\VehicleAssistant;
use App\Models\VehicleAssignmentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleAssignmentService
{
    public function __construct(private readonly LoanAvailabilityService $availability) {}

    public function assign(G005M019VehicleReservation $reservation, ?int $driverId, ?int $assistantId, string $reason, ?User $actor): void
    {
        $vehicle = $reservation->vehicle;
        if (! $vehicle || ! $reservation->start_time || ! $reservation->end_time) {
            throw ValidationException::withMessages(['vehicle' => 'Jadwal atau kendaraan tidak valid.']);
        }
        if (! $driverId) {
            throw ValidationException::withMessages(['driver_id' => 'Pengemudi harus ditetapkan.']);
        }

        $driver = G008M018Driver::query()->lockForUpdate()->find($driverId);
        if (! $driver || ! $driver->user()->exists()) {
            throw ValidationException::withMessages(['driver_id' => 'Data pengemudi tidak valid.']);
        }
        if (! $this->availability->driverIsAvailable($driverId, $reservation->start_time, $reservation->end_time, $reservation->getKey())) {
            throw ValidationException::withMessages(['driver_id' => 'Pengemudi sudah memiliki tugas lain pada jadwal ini.']);
        }

        if ($vehicle->requires_assistant) {
            if (! $assistantId) {
                throw ValidationException::withMessages(['assistant_id' => 'Bus wajib memiliki kenek.']);
            }
            $assistant = VehicleAssistant::query()->lockForUpdate()->find($assistantId);
            if (! $assistant || ! $assistant->is_active
                || ! $this->availability->assistantIsAvailable($assistantId, $reservation->start_time, $reservation->end_time, $reservation->getKey())) {
                throw ValidationException::withMessages(['assistant_id' => 'Kenek tidak aktif atau sudah memiliki tugas lain.']);
            }
        } else {
            $assistantId = null;
        }

        $oldDriver = $reservation->g008_m018_driver_id;
        $oldAssistant = $reservation->vehicle_assistant_id;
        if ((int) $oldDriver === $driverId && (int) $oldAssistant === (int) $assistantId) {
            return;
        }

        $reservation->update(['g008_m018_driver_id' => $driverId, 'vehicle_assistant_id' => $assistantId]);
        VehicleAssignmentHistory::query()->create([
            'vehicle_reservation_id' => $reservation->getKey(),
            'old_driver_id' => $oldDriver,
            'new_driver_id' => $driverId,
            'old_assistant_id' => $oldAssistant,
            'new_assistant_id' => $assistantId,
            'changed_by' => $actor?->id,
            'changed_by_name' => $actor?->name,
            'reason' => $reason,
        ]);
    }

    public function change(string $id, User $actor, array $data): void
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Alasan perubahan wajib diisi.']);
        }

        $activityId = G005M019VehicleReservation::query()->whereKey($id)->value('g004_m008_activity_id');
        DB::transaction(function () use ($activityId, $id, $actor, $data, $reason): void {
            if ($activityId) {
                G004M008Activity::query()->lockForUpdate()->findOrFail($activityId);
            }
            $reservation = G005M019VehicleReservation::query()->lockForUpdate()->findOrFail($id);
            if (! $actor->managesReservation($reservation)
                || ! in_array($reservation->status, [ReservationStatus::Approved->value, ReservationStatus::CheckedOut->value], true)) {
                throw ValidationException::withMessages(['status' => 'Penugasan tidak boleh diubah pada status ini.']);
            }
            $this->assign(
                $reservation,
                filled($data['driver_id'] ?? null) ? (int) $data['driver_id'] : null,
                filled($data['assistant_id'] ?? null) ? (int) $data['assistant_id'] : null,
                $reason,
                $actor,
            );
        }, 3);
    }
}
