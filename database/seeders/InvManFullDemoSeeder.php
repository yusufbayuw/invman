<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\G002M002ItemType;
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
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\VehicleAssistant;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Opt-in demonstration fixtures. NEVER called from DatabaseSeeder.
 * All new data is isolated by [DEMO-INVMAN] identifiers and demo-only units.
 * No submitted bookings, real-asset holds, or published workflows are created.
 */
class InvManFullDemoSeeder extends Seeder
{
    private const PREFIX = '[DEMO-INVMAN]';

    public function run(): void
    {
        $production = app()->environment('production') || config('app.env') === 'production';
        if ($production && config('security.full_demo_command_authorized') !== true) {
            throw new RuntimeException('Seeder demo production hanya melalui perintah invman:seed-full-demo dengan konfirmasi interaktif.');
        }

        $password = config('security.seed_demo_password');
        Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();

        // No global role/permission sync in production. Operator provisions roles normally.
        foreach ([config('role.sarpras')] as $roleName) {
            if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                throw new RuntimeException("Role {$roleName} belum ada. Jalankan RoleSeeder secara terpisah setelah backup.");
            }
        }

        $privileged = (bool) config('security.full_demo_create_privileged', false);
        if ($privileged) {
            foreach ([config('role.admin'), config('role.fasilitas')] as $roleName) {
                if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                    throw new RuntimeException("Role {$roleName} belum ada.");
                }
            }
        }

        DB::transaction(function () use ($password, $privileged): void {
            // Never use real school units/assets for fixtures.
            $unitA = G001M001Unit::query()->firstOrCreate(['name' => self::PREFIX.' SD Demo']);
            $unitB = G001M001Unit::query()->firstOrCreate(['name' => self::PREFIX.' SMA Demo']);
            $management = G002M003ItemManagement::query()->firstOrCreate(
                ['name' => self::PREFIX.' Pengelola Fasilitas'],
                ['description' => 'Khusus data demonstrasi; bukan aset operasional.'],
            );
            $itemType = G002M002ItemType::query()->firstOrCreate(
                ['name' => self::PREFIX.' Peralatan Demo'],
                ['description' => 'Barang latihan InvMan.'],
            );
            $building = G003M004Building::query()->firstOrCreate(
                ['name' => self::PREFIX.' Gedung Latihan'], ['location' => 'Lokasi fiktif, bukan fasilitas nyata'],
            );
            $floor = G003M005Floor::query()->firstOrCreate(
                ['g003_m004_building_id' => $building->id, 'name' => 'Lantai Demo'], ['map' => ''],
            );
            $room = G003M006Room::query()->firstOrCreate(
                ['g003_m005_floor_id' => $floor->id, 'name' => self::PREFIX.' Aula Latihan'],
                ['g001_m001_unit_id' => $unitA->id, 'g002_m003_item_management_id' => $management->id,
                    'is_borrowable' => true, 'capacity' => 40, 'status' => 'tersedia'],
            );
            $roomB = G003M006Room::query()->firstOrCreate(
                ['g003_m005_floor_id' => $floor->id, 'name' => self::PREFIX.' Lab SMA'],
                ['g001_m001_unit_id' => $unitB->id, 'g002_m003_item_management_id' => $management->id,
                    'is_borrowable' => true, 'capacity' => 25, 'status' => 'tersedia'],
            );

            // Each demo identity represents ONE employee. Only ONE account holds each
            // global role; job titles are separate, scoped assignments, not extra roles.
            $borrowerA = $this->demoUser('sarpras.sd', 'Dewi - Sarpras SD', $unitA, $password, config('role.sarpras'));
            $head = $this->demoUser('atasan.sd', 'Rizal - Atasan SD', $unitA, $password);
            $staff = $this->demoUser('staf.fasilitas', 'Nina - Staf Fasilitas', $unitA, $password);
            $supervisor = $this->demoUser('spv.fasilitas', 'Lukman - Supervisor Fasilitas', $unitA, $password);
            $manager = $this->demoUser('manager.fasilitas', 'Rani - Manajer Fasilitas', $unitA, $password,
                $privileged ? config('role.fasilitas') : null);
            $assetManager = $this->demoUser('pengelola.aset', 'Fajar - Pengelola Aset', $unitA, $password);
            $lead = $this->demoUser('koordinator', 'Bayu - Koordinator Teknisi', $unitA, $password);
            $technician = $this->demoUser('teknisi', 'Arif - Teknisi Lapangan', $unitA, $password);
            $driverUser = $this->demoUser('pengemudi', 'Dedi - Pengemudi', $unitA, $password);
            if ($privileged) {
                $this->demoUser('admin', 'Ayu - Administrator', $unitA, $password, config('role.admin'));
            }

            $assetManager->itemManagements()->syncWithoutDetaching([$management->id]);
            $assetManager->assignRole(config('role.pengelola_barang'));
            $driver = G008M018Driver::query()->firstOrCreate(
                ['user_id' => $driverUser->id],
                ['sim_number' => 'DEMO-INVMAN-SIM-001', 'sim_type' => 'A'],
            );
            VehicleAssistant::query()->firstOrCreate(
                ['name' => self::PREFIX.' Kenek Demo'], ['is_active' => true, 'notes' => 'Fixture latihan'],
            );
            $car = G008M017Vehicle::query()->firstOrCreate(
                ['license_plate' => 'D 9901 DMO'],
                ['name' => self::PREFIX.' Minibus Demo', 'g001_m001_unit_id' => $unitA->id,
                    'g002_m003_item_management_id' => $management->id, 'default_driver_id' => $driver->id,
                    'requires_assistant' => false, 'is_borrowable' => true, 'status' => 'tersedia'],
            );
            G008M017Vehicle::query()->firstOrCreate(
                ['license_plate' => 'D 9902 DMO'],
                ['name' => self::PREFIX.' Bus Demo', 'g001_m001_unit_id' => $unitA->id,
                    'g002_m003_item_management_id' => $management->id, 'default_driver_id' => $driver->id,
                    'requires_assistant' => true, 'is_borrowable' => true, 'status' => 'tersedia'],
            );

            foreach (['D 9901 DMO' => self::PREFIX.' Minibus Demo', 'D 9902 DMO' => self::PREFIX.' Bus Demo'] as $plate => $expected) {
                if (G008M017Vehicle::query()->where('license_plate', $plate)->where('name', '!=', $expected)->exists()) {
                    throw new RuntimeException("Nomor kendaraan demo {$plate} sudah dipakai aset lain.");
                }
            }

            $projector = $this->demoItem('PRJ-01', 'Proyektor Latihan', 4, $unitA, $management, $itemType, $room);
            $this->demoItem('LTP-01', 'Laptop Latihan', 3, $unitB, $management, $itemType, $roomB);
            $damaged = $this->demoItem('AC-01', 'AC Perlu Perbaikan', 1, $unitA, $management, $itemType, $room);

            $titles = [];
            foreach (['Atasan Unit', 'Kepala Sarpras', 'Staf Fasilitas', 'Supervisor Fasilitas',
                'Manager Fasilitas', 'Koordinator Teknisi', 'Teknisi'] as $name) {
                $titles[$name] = WorkOrderJobTitle::query()->firstOrCreate(
                    ['name' => self::PREFIX.' '.$name], ['is_active' => true],
                );
            }
            $this->assignment($head, $titles['Atasan Unit'], $unitA);
            $this->assignment($borrowerA, $titles['Kepala Sarpras'], $unitA, $head);
            $this->assignment($staff, $titles['Staf Fasilitas'], $unitA, $supervisor);
            $this->assignment($supervisor, $titles['Supervisor Fasilitas'], $unitA, $manager);
            $this->assignment($manager, $titles['Manager Fasilitas'], $unitA);
            $this->assignment($lead, $titles['Koordinator Teknisi'], $unitA);
            $this->assignment($technician, $titles['Teknisi'], $unitA, $lead);

            TicketTeamMember::query()->firstOrCreate(['user_id' => $lead->id], [
                'team_name' => self::PREFIX.' Tim Lapangan', 'team_type' => 'field',
                'position' => 'lead', 'g002_m003_item_management_id' => $management->id, 'is_active' => true,
            ]);
            TicketTeamMember::query()->firstOrCreate(['user_id' => $technician->id], [
                'team_name' => self::PREFIX.' Tim Lapangan', 'team_type' => 'field',
                'position' => 'technician', 'supervisor_user_id' => $lead->id,
                'g002_m003_item_management_id' => $management->id, 'is_active' => true,
            ]);

            if (! WorkOrderWorkflow::query()->where('g001_m001_unit_id', $unitA->id)->exists()) {
                WorkOrderWorkflow::query()->create([
                    'g001_m001_unit_id' => $unitA->id, 'version' => 1, 'status' => 'draft',
                    'created_by' => $manager->id,
                    'assign_job_title_id' => $titles['Supervisor Fasilitas']->id,
                    'close_job_title_id' => $titles['Manager Fasilitas']->id,
                    'dispatch_job_title_id' => $titles['Teknisi']->id,
                    'steps' => [
                        ['key' => 'diketahui_atasan', 'label' => 'Diketahui Atasan Unit',
                            'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
                        ['key' => 'diterima_staf', 'label' => 'Diterima Staf Fasilitas',
                            'checkpoint' => 'start', 'resolver' => 'job_title',
                            'job_title_id' => $titles['Staf Fasilitas']->id],
                        ['key' => 'ditinjau_spv', 'label' => 'Ditinjau Supervisor',
                            'checkpoint' => 'start', 'resolver' => 'job_title',
                            'job_title_id' => $titles['Supervisor Fasilitas']->id],
                        ['key' => 'disahkan_manager', 'label' => 'Pengesahan Akhir',
                            'checkpoint' => 'final', 'resolver' => 'job_title',
                            'job_title_id' => $titles['Manager Fasilitas']->id],
                    ],
                ]);
            }

            if ($privileged) {
                // Demo-only unit: publish BEFORE creating tickets so their immutable
                // approval snapshots include realistic delegated approvers.
                $admin = User::query()->where('username', 'invman.demo.admin')->firstOrFail();
                $demoWorkflow = WorkOrderWorkflow::query()
                    ->where('g001_m001_unit_id', $unitA->id)->orderByDesc('version')->firstOrFail();
                if ($demoWorkflow->status === 'draft') {
                    app(\App\Services\WorkOrderWorkflowService::class)->publish($demoWorkflow, $admin);
                }
            }

            $this->draft($borrowerA, 'Pinjam Proyektor', [
                ['type' => 'item', 'item_id' => $projector->id, 'quantity' => 2],
            ]);
            $this->draft($borrowerA, 'Pinjam Aula', [
                ['type' => 'room', 'room_id' => $room->id],
            ]);
            $this->draft($borrowerA, 'Pinjam Minibus', [
                ['type' => 'vehicle', 'vehicle_id' => $car->id],
            ]);

            $category = TicketCategory::query()->firstOrCreate(
                ['slug' => 'invman-full-demo-maintenance'],
                ['name' => self::PREFIX.' Perawatan', 'is_active' => true, 'reporting_enabled' => true],
            );
            if (! $category->is_active || ! $category->reporting_enabled) {
                throw new RuntimeException('Kategori demo sudah dinonaktifkan; seeder tidak mengubah keputusan admin.');
            }
            $this->ticket($borrowerA, $category, 'assetless', 'Laporan tanpa nomor inventaris',
                'Contoh lampu mati di koridor; petugas dapat mengidentifikasi lokasi dan menindaklanjuti.');
            $this->ticket($borrowerA, $category, 'damaged-item', 'Perbaikan AC contoh',
                'AC latihan berbunyi keras dan tidak dapat digunakan; cek safety hold pada aset demo.', $damaged);
        }, 3);

        $this->command?->info('Fixture [DEMO-INVMAN] tersedia: 2 unit, satu akun per jabatan/peran (9 + opsional Admin), aset, 3 draf pinjaman, 2 tiket dan workflow DRAFT.');
        $this->command?->warn('Peminjaman tetap draf; workflow unit demo dipublikasikan hanya dengan --include-privileged agar approval dapat diperagakan.');
    }

    private function demoUser(string $suffix, string $name, G001M001Unit $unit, string $password, ?string $role = null): User
    {
        $username = 'invman.demo.'.$suffix;
        $email = $username.'@invman.invalid';
        $user = User::query()->where('username', $username)->first();
        if ($user) {
            if ($user->email !== $email || (int) $user->g001_m001_unit_id !== (int) $unit->id) {
                throw new RuntimeException("Username demo {$username} sudah digunakan akun dengan identitas atau unit berbeda.");
            }
            // Never silently escalate existing credentials to an admin/facility role.
            if ($role && ! $user->hasRole($role)) {
                throw new RuntimeException("Akun demo {$username} sudah ada tanpa role {$role}; tidak ada eskalasi akses otomatis.");
            }
            // Never reset an existing user's password, role, unit, enablement or name.
            return $user;
        }
        $user = User::query()->create([
            'username' => $username, 'email' => $email, 'name' => self::PREFIX.' '.$name,
            'g001_m001_unit_id' => $unit->id, 'email_verified_at' => now(),
            'password' => Hash::make($password), 'is_enabled' => true,
        ]);
        if ($role) {
            $user->assignRole($role);
        }
        return $user;
    }

    private function demoItem(string $suffix, string $name, int $quantity, G001M001Unit $unit,
        G002M003ItemManagement $management, G002M002ItemType $type, G003M006Room $room): G002M007Item
    {
        $code = 'INVMAN-DEMO-'.$suffix;
        $existing = G002M007Item::query()->where('code', $code)->first();
        if ($existing && ($existing->name !== self::PREFIX.' '.$name
            || (int) $existing->g001_m001_unit_id !== (int) $unit->id)) {
            throw new RuntimeException("Kode barang demo {$code} sudah digunakan barang lain.");
        }
        return G002M007Item::query()->firstOrCreate(['code' => $code], [
            'name' => self::PREFIX.' '.$name,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'g002_m002_item_type_id' => $type->id,
            'g003_m006_room_id' => $room->id,
            'quantity' => (string) $quantity,
            'is_borrowable' => true,
        ]);
    }

    private function assignment(User $user, WorkOrderJobTitle $title, G001M001Unit $unit, ?User $supervisor = null): void
    {
        WorkOrderAssignment::query()->firstOrCreate([
            'user_id' => $user->id, 'work_order_job_title_id' => $title->id,
            'g001_m001_unit_id' => $unit->id,
        ], ['supervisor_user_id' => $supervisor?->id, 'is_active' => true]);
    }

    /** Only drafts: never reserve capacity or appear as approved bookings on the public schedule. */
    private function draft(User $borrower, string $label, array $needs): void
    {
        $name = self::PREFIX.' '.$label;
        if (G004M008Activity::query()->where('user_id', $borrower->id)->where('name', $name)->exists()) {
            return;
        }
        $start = now()->addDays(30)->startOfDay()->addHours(9);
        app(LoanRequestService::class)->saveDraft($borrower, [
            'name' => $name, 'description' => 'Latihan pengajuan InvMan; bukan peminjaman fasilitas nyata.',
            'notes' => 'Jangan proses pada aset operasional sebenarnya.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'needs' => $needs,
        ]);
    }

    private function ticket(User $reporter, TicketCategory $category, string $key, string $title,
        string $description, ?G002M007Item $item = null): void
    {
        $sourceKey = 'invman:full-demo:'.$key;
        if (Ticket::query()->where('source_key', $sourceKey)->exists()) {
            return;
        }
        $data = [
            'ticket_category_id' => $category->id,
            'title' => self::PREFIX.' '.$title,
            'description' => $description,
            'location' => self::PREFIX.' Gedung Latihan',
            'reported_urgency' => 'normal',
        ];
        if ($item) {
            $data['asset_type'] = 'item';
            $data['asset_id'] = $item->id;
        }
        $ticket = app(TicketService::class)->open($reporter, $data);
        $ticket->forceFill(['source_key' => $sourceKey])->saveQuietly();
        // Keep inactive demo tickets from escalating to every real facility/admin account.
        // If a human approves the ticket, the real maintenance service sets normal SLA deadlines.
        $ticket->maintenance?->forceFill([
            'response_due_at' => null, 'resolution_due_at' => null,
        ])->save();
    }
}
