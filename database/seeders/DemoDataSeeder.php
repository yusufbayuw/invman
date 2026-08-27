<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M002ItemType;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $adm = G001M001Unit::query()->where('name', 'ADM')->firstOrFail();
        $sd = G001M001Unit::query()->where('name', 'SD')->firstOrFail();
        $smp = G001M001Unit::query()->where('name', 'SMP')->firstOrFail();
        $facility = G002M003ItemManagement::query()->where('name', 'Fasilitas')->firstOrFail();
        $it = G002M003ItemManagement::query()->where('name', 'IT')->firstOrFail();
        $multimedia = G002M002ItemType::query()->where('name', 'Multimedia & Audio Visual')->firstOrFail();
        $computer = G002M002ItemType::query()->where('name', 'Komputer & Laptop')->firstOrFail();
        $accessory = G002M002ItemType::query()->where('name', 'Aksesori & Periferal')->firstOrFail();
        $aula1 = G003M006Room::query()->where('name', 'Aula Lantai 1')->firstOrFail();
        $projector = $this->item('INV-PROJ-001', 'Proyektor Epson EB-X06', 4, $adm, $facility, $multimedia, $aula1);
        $this->item('INV-SPKR-001', 'Speaker Portable JBL EON', 2, $adm, $facility, $multimedia, $aula1);
        $this->item('INV-LPTP-001', 'Laptop Presentasi Lenovo ThinkPad', 3, $adm, $it, $computer, $aula1);
        $this->item('INV-HDMI-001', 'Kabel HDMI 10 Meter', 6, $adm, $it, $accessory, $aula1);

        $minibus = G008M017Vehicle::query()->updateOrCreate(
            ['license_plate' => 'D 1234 INV'],
            [
                'g001_m001_unit_id' => $adm->id,
                'name' => 'Toyota HiAce Demo',
                'stnk_date' => now()->addYear()->toDateString(),
                'kir_date' => now()->addMonths(6)->toDateString(),
                'capacity' => 14,
                'is_borrowable' => true,
                'status' => 'tersedia',
            ],
        );

        G008M017Vehicle::query()->updateOrCreate(
            ['license_plate' => 'D 5678 INV'],
            [
                'g001_m001_unit_id' => $adm->id,
                'name' => 'Toyota Avanza Demo',
                'stnk_date' => now()->addYear()->toDateString(),
                'kir_date' => null,
                'capacity' => 7,
                'is_borrowable' => true,
                'status' => 'tersedia',
            ],
        );

        $approvedStart = now()->addDay()->setTime(9, 0);
        $approvedEnd = $approvedStart->copy()->addHours(2);
        $approvedActivity = G004M008Activity::query()->updateOrCreate(
            ['name' => '[DEMO] Rapat Koordinasi SD'],
            [
                'user_id' => User::query()->where('username', 'sarpras.sd')->firstOrFail()->id,
                'g001_m001_unit_id' => $sd->id,
                'description' => 'Contoh kegiatan yang telah disetujui dan tampil pada jadwal publik.',
                'notes' => 'Data demo dari seeder.',
                'start_time' => $approvedStart,
                'end_time' => $approvedEnd,
                'status' => ReservationStatus::Approved->value,
                'cancelled_at' => null,
            ],
        );

        G005M009ItemReservation::query()->updateOrCreate(
            [
                'g004_m008_activity_id' => $approvedActivity->id,
                'g002_m007_item_id' => $projector->id,
            ],
            [
                'quantity' => 1,
                'start_time' => $approvedStart,
                'end_time' => $approvedEnd,
                'status' => ReservationStatus::Approved->value,
            ],
        );
        G005M010RoomReservation::query()->updateOrCreate(
            [
                'g004_m008_activity_id' => $approvedActivity->id,
                'g003_m006_room_id' => $aula1->id,
            ],
            [
                'start_time' => $approvedStart,
                'end_time' => $approvedEnd,
                'status' => ReservationStatus::Approved->value,
            ],
        );
        $approvedActivity->updateQuietly(['status' => ReservationStatus::Approved->value]);

        $submittedStart = now()->addDays(3)->setTime(7, 30);
        $submittedEnd = $submittedStart->copy()->addHours(8);
        $submittedActivity = G004M008Activity::query()->updateOrCreate(
            ['name' => '[DEMO] Kunjungan Belajar SMP'],
            [
                'user_id' => User::query()->where('username', 'sarpras.smp')->firstOrFail()->id,
                'g001_m001_unit_id' => $smp->id,
                'description' => 'Contoh pengajuan kendaraan yang masih menunggu keputusan fasilitas.',
                'notes' => 'Pengemudi ditentukan setelah pengajuan disetujui.',
                'start_time' => $submittedStart,
                'end_time' => $submittedEnd,
                'status' => ReservationStatus::Submitted->value,
                'cancelled_at' => null,
            ],
        );

        G005M019VehicleReservation::query()->updateOrCreate(
            [
                'g004_m008_activity_id' => $submittedActivity->id,
                'g008_m017_vehicle_id' => $minibus->id,
            ],
            [
                'g008_m018_driver_id' => null,
                'start_time' => $submittedStart,
                'end_time' => $submittedEnd,
                'status' => ReservationStatus::Submitted->value,
            ],
        );
        $submittedActivity->updateQuietly(['status' => ReservationStatus::Submitted->value]);
    }

    private function item(
        string $code,
        string $name,
        int $quantity,
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        G002M002ItemType $type,
        G003M006Room $room,
    ): G002M007Item {
        $item = G002M007Item::query()->firstOrNew(['code' => $code]);
        $item->fill([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'g002_m002_item_type_id' => $type->id,
            'g003_m006_room_id' => $room->id,
            'name' => $name,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);
        $item->save();

        return $item;
    }
}
