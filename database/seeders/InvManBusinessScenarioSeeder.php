<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G003M004Building;
use App\Models\G003M005Floor;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\G008M018Driver;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\LoanRequestService;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Companion to InvManRoleMatrixDemoSeeder. Creates operational SAMPLE RECORDS
 * through application services. Not installed in DatabaseSeeder.
 *
 * Local/testing ONLY. Never run with a real institution's users/assets.
 * Submitted reservations intentionally follow the normal hold/notification flow.
 */
class InvManBusinessScenarioSeeder extends Seeder
{
    private const PREFIX = '[DEMO-ORG]';

    private const UNITS = [
        'tk' => 'TK',
        'sd' => 'SD',
        'smp' => 'SMP',
        'sma' => 'SMA',
        'universitas' => 'Universitas',
    ];

    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)
            || ! in_array((string) config('app.env'), ['local', 'testing'], true)
            || config('security.seed_demo_users') !== true) {
            throw new RuntimeException('Seeder skenario operasional hanya untuk local/testing terisolasi dan SEED_DEMO_USERS=true.');
        }

        $management = G002M003ItemManagement::query()
            ->where('name', self::PREFIX.' Fasilitas')->firstOrFail();
        $manager = $this->demoUser('man.fas');
        $technician = $this->demoUser('teknisi');
        $itManager = $this->demoUser('it');
        $libraryManager = $this->demoUser('perpus');
        $driverUser = $this->demoUser('driver');
        $driver = G008M018Driver::query()->where('user_id', $driverUser->id)->firstOrFail();
        if ($driver->sim_number !== 'DEMO-ORG-SIM-A-001') {
            throw new RuntimeException('Data SIM akun driver telah berubah; tidak ditimpa seeder.');
        }

        $data = [];
        foreach (self::UNITS as $slug => $label) {
            $unit = G001M001Unit::query()->where('name', self::PREFIX.' '.$label)->firstOrFail();
            $borrower = $this->demoUser('sarpras.'.($slug === 'universitas' ? 'univ' : $slug));
            if ((int) $borrower->g001_m001_unit_id !== (int) $unit->id) {
                throw new RuntimeException("Sarpras {$slug} tidak cocok dengan unit demo.");
            }

            $building = G003M004Building::query()->firstOrCreate(
                ['name' => self::PREFIX.' Gedung '.$label],
                ['location' => 'Lokasi fiktif untuk simulasi '.$label],
            );
            $floor = G003M005Floor::query()->firstOrCreate(
                ['g003_m004_building_id' => $building->id, 'name' => self::PREFIX.' Lantai 1'],
                ['map' => ''],
            );
            $hall = G003M006Room::query()->firstOrCreate(
                ['g003_m005_floor_id' => $floor->id, 'name' => self::PREFIX.' Aula '.$label],
                ['g001_m001_unit_id' => $unit->id, 'g002_m003_item_management_id' => $management->id,
                    'capacity' => 40, 'is_borrowable' => true, 'status' => 'tersedia'],
            );
            $archive = G003M006Room::query()->firstOrCreate(
                ['g003_m005_floor_id' => $floor->id, 'name' => self::PREFIX.' Gudang '.$label],
                ['g001_m001_unit_id' => $unit->id, 'g002_m003_item_management_id' => $management->id,
                    'capacity' => 4, 'is_borrowable' => false, 'status' => 'tersedia'],
            );
            if ((int) $hall->g001_m001_unit_id !== (int) $unit->id
                || (int) $archive->g001_m001_unit_id !== (int) $unit->id) {
                throw new RuntimeException('Ruangan demo tidak cocok dengan unit; data tidak ditimpa.');
            }

            $plate = 'D '.(9501 + array_search($slug, array_keys(self::UNITS), true)).' DMO';
            $vehicle = G008M017Vehicle::query()->firstOrCreate(
                ['license_plate' => $plate],
                ['name' => self::PREFIX.' '.($slug === 'universitas' ? 'Bus' : 'Minibus').' '.$label,
                    'g001_m001_unit_id' => $unit->id,
                    'g002_m003_item_management_id' => $management->id,
                    'default_driver_id' => $driver->id,
                    'requires_assistant' => $slug === 'universitas',
                    'capacity' => $slug === 'universitas' ? 30 : 6,
                    'is_borrowable' => true, 'status' => 'tersedia'],
            );
            if ((int) $vehicle->g001_m001_unit_id !== (int) $unit->id
                || (int) $vehicle->default_driver_id !== (int) $driver->id) {
                throw new RuntimeException('Kendaraan demo tidak cocok dengan driver/unit; data tidak ditimpa.');
            }
            $data[$slug] = compact('unit', 'borrower', 'hall', 'archive', 'vehicle');

            $this->draft($borrower, 'Ruang '.$label, [['type' => 'room', 'room_id' => $hall->id]], 40);
            $this->draft($borrower, 'Kendaraan '.$label, [['type' => 'vehicle', 'vehicle_id' => $vehicle->id]], 41);
        }

        $sdIt = $this->item('sd', 'IT');
        $smpIt = $this->item('smp', 'IT');
        $smaLibrary = $this->item('sma', 'LIB');
        $universityIt = $this->item('universitas', 'IT');
        $universityLibrary = $this->item('universitas', 'LIB');

        // One submitted request awaits decisions, one approved, one rejected,
        // one partially approved, one cancelled, one driver-assigned vehicle.
        $this->scenarioLoan($data['sd']['borrower'], 'SD Menunggu Persetujuan IT',
            [['type' => 'item', 'item_id' => $sdIt->id, 'quantity' => 1]], 50);

        $approved = $this->scenarioLoan($data['smp']['borrower'], 'SMP Laptop Disetujui',
            [['type' => 'item', 'item_id' => $smpIt->id, 'quantity' => 1]], 51);
        $this->decide($approved, 'item', $itManager, ReservationStatus::Approved);

        $rejected = $this->scenarioLoan($data['sma']['borrower'], 'SMA Buku Ditolak',
            [['type' => 'item', 'item_id' => $smaLibrary->id, 'quantity' => 1]], 52);
        $this->decide($rejected, 'item', $libraryManager, ReservationStatus::Rejected,
            'Jumlah buku referensi tidak dapat dipinjam pada kegiatan tersebut.');

        $partial = $this->scenarioLoan($data['universitas']['borrower'], 'Universitas Persetujuan Sebagian',
            [['type' => 'item', 'item_id' => $universityIt->id, 'quantity' => 1],
                ['type' => 'item', 'item_id' => $universityLibrary->id, 'quantity' => 1]], 53);
        $this->decide($partial, 'item', $itManager, ReservationStatus::Approved, null, $universityIt->id);
        $this->decide($partial, 'item', $libraryManager, ReservationStatus::Rejected,
            'Koleksi perpustakaan sedang dibatasi untuk kegiatan tersebut.', $universityLibrary->id);

        $cancelled = $this->scenarioLoan($data['tk']['borrower'], 'TK Pengajuan Ruangan Dibatalkan',
            [['type' => 'room', 'room_id' => $data['tk']['hall']->id]], 54);
        if ($cancelled->status === ReservationStatus::Submitted->value) {
            $this->asActor($data['tk']['borrower'],
                fn () => app(LoanRequestService::class)->cancel($cancelled));
        }

        $vehicleActivity = $this->scenarioLoan($data['sd']['borrower'], 'SD Kendaraan Driver Ditugaskan',
            [['type' => 'vehicle', 'vehicle_id' => $data['sd']['vehicle']->id]], 55);
        $this->decide($vehicleActivity, 'vehicle', $manager, ReservationStatus::Approved);

        $category = TicketCategory::query()->firstOrCreate(
            ['slug' => 'invman-organization-demo'],
            ['name' => self::PREFIX.' Laporan Fasilitas', 'is_active' => true, 'reporting_enabled' => true],
        );
        if (! $category->is_active || ! $category->reporting_enabled) {
            throw new RuntimeException('Kategori tiket demo telah dinonaktifkan; seeder tidak mengubahnya.');
        }

        // Tickets demonstrate real service transitions. Work Order approval
        // workflows remain DRAFT, so these tickets use facility fallback.
        $this->scenarioTicket('tk-open', $data['tk']['borrower'], $category,
            'Lampu koridor TK mati', null, 'open', $manager, $technician);
        $this->scenarioTicket('sd-triaged', $data['sd']['borrower'], $category,
            'Kipas aula SD bermasalah', $data['sd']['hall'], 'triaged', $manager, $technician);
        $this->scenarioTicket('smp-progress', $data['smp']['borrower'], $category,
            'Pintu aula SMP rusak', $data['smp']['hall'], 'in_progress', $manager, $technician);
        $this->scenarioTicket('sma-parts', $data['sma']['borrower'], $category,
            'Pendingin aula SMA perlu suku cadang', $data['sma']['hall'], 'waiting_parts', $manager, $technician);
        $this->scenarioTicket('univ-closed', $data['universitas']['borrower'], $category,
            'Proyektor aula universitas sudah diperbaiki', $data['universitas']['hall'], 'closed', $manager, $technician);
        $this->scenarioTicket('sd-cancelled', $data['sd']['borrower'], $category,
            'Laporan duplikat mengenai lampu lorong', null, 'cancelled', $manager, $technician);
        $this->scenarioTicket('sma-resolved', $data['sma']['borrower'], $category,
            'Perbaikan lampu pintu aula menunggu verifikasi', $data['sma']['archive'], 'resolved', $manager, $technician);

        $this->command?->info('Skenario tambahan dibuat: driver, 5 kendaraan, 10 ruangan, 10 draf ruang/kendaraan, 6 pengajuan dengan status beragam, 7 tiket Work Order.');
        $this->command?->warn('Tidak membuat checkout/return palsu. Serah-terima dua pihak dan pengembalian harus diuji melalui service/fitur yang sesungguhnya.');
    }

    private function demoUser(string $username): User
    {
        $user = User::query()->where('username', $username)
            ->where('email', $username.'@invman.invalid')
            ->where('name', 'like', self::PREFIX.' %')->first();
        if (! $user || ! $user->is_enabled) {
            throw new RuntimeException("Akun {$username} belum tersedia sebagai pengguna demo aktif.");
        }
        return $user;
    }

    private function item(string $slug, string $suffix): G002M007Item
    {
        return G002M007Item::query()->where('code', 'INVMAN-ORG-'.strtoupper($slug).'-'.$suffix)->firstOrFail();
    }

    private function asActor(User $actor, callable $callback): mixed
    {
        $original = auth()->user();
        auth()->setUser($actor);
        try {
            return $callback();
        } finally {
            if ($original) {
                auth()->setUser($original);
            } else {
                auth()->logout();
            }
        }
    }

    private function requestData(string $name, array $needs, int $days): array
    {
        $start = now()->addDays($days)->startOfDay()->addHours(9);
        return [
            'name' => self::PREFIX.' '.$name,
            'description' => 'Skenario UAT yang hanya memakai aset dan unit demo.',
            'notes' => 'Pengajuan demonstrasi; jangan dicampur dengan jadwal operasional.',
            'start_time' => $start, 'end_time' => $start->copy()->addHours(2),
            'needs' => $needs,
        ];
    }

    private function draft(User $borrower, string $name, array $needs, int $days): void
    {
        $title = self::PREFIX.' '.$name;
        if (G004M008Activity::query()->where('user_id', $borrower->id)->where('name', $title)->exists()) {
            return;
        }
        app(LoanRequestService::class)->saveDraft($borrower, $this->requestData($name, $needs, $days));
    }

    private function scenarioLoan(User $borrower, string $name, array $needs, int $days): G004M008Activity
    {
        $title = self::PREFIX.' '.$name;
        $existing = G004M008Activity::query()->where('user_id', $borrower->id)->where('name', $title)->first();
        if ($existing) {
            return $existing;
        }
        return app(LoanRequestService::class)->submit($borrower, $this->requestData($name, $needs, $days));
    }

    private function decide(
        G004M008Activity $activity,
        string $type,
        User $custodian,
        ReservationStatus $status,
        ?string $reason = null,
        ?int $itemId = null,
    ): void {
        $reservation = $type === 'vehicle'
            ? $activity->vehicle_reservation()->firstOrFail()
            : $activity->item_reservation()->when($itemId,
                fn ($query) => $query->where('g002_m007_item_id', $itemId))->firstOrFail();
        if ($reservation->status !== ReservationStatus::Submitted->value) {
            return;
        }
        $this->asActor($custodian, fn () => app(LoanRequestService::class)
            ->processReservation($type, $reservation->id, $status, $reason));
    }

    private function scenarioTicket(
        string $key, User $borrower, TicketCategory $category,
        string $label, ?G003M006Room $room, string $target,
        User $manager, User $technician,
    ): void {
        $source = 'invman:org-demo:ticket:'.$key;
        if (Ticket::query()->where('source_key', $source)->exists()) {
            return;
        }
        $ticket = app(TicketService::class)->open($borrower, [
            'ticket_category_id' => $category->id,
            'title' => self::PREFIX.' '.$label,
            'description' => 'Skenario contoh: laporan kerusakan fasilitas dengan alur yang dapat diuji.',
            'location' => self::PREFIX.' '.$borrower->unit->name,
            'reported_urgency' => 'normal',
            ...($room ? ['asset_type' => 'room', 'asset_id' => $room->id] : []),
        ]);
        $ticket->forceFill(['source_key' => $source])->saveQuietly();
        if ($target === 'open') {
            return;
        }

        $service = app(TicketMaintenanceService::class);
        if ($target === 'cancelled') {
            $service->cancel($ticket, $manager, 'Tiket contoh dibatalkan karena laporan duplikat.');
            return;
        }
        $service->approve($ticket, $manager, $technician->id, 'normal', 'Ditugaskan kepada teknisi demo.');
        if ($target === 'triaged') {
            return;
        }
        $service->accept($ticket, $technician);
        if ($target === 'in_progress') {
            return;
        }
        if ($target === 'waiting_parts') {
            $service->waiting($ticket, $technician, true, 'Menunggu komponen pengganti untuk pekerjaan.');
            return;
        }
        $service->finish($ticket, $technician,
            'Komponen instalasi awal longgar dan menyebabkan gangguan.',
            'Komponen diperbaiki dan diuji kembali sesuai prosedur.',
            [], 'complete');
        if ($target === 'resolved') {
            return;
        }
        $service->verify($ticket, $borrower, true, 'Hasil pekerjaan telah diperiksa oleh unit.');
        $service->close($ticket, $manager, true, 'Perbaikan telah selesai dan aman digunakan.');
    }
}
