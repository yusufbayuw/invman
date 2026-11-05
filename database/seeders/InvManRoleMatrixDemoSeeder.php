<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\G002M002ItemType;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G008M018Driver;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\LoanRequestService;
use App\Services\WorkOrderWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Explicit, isolated non-production fixtures for the TK-to-university organization.
 *
 * Deliberately NOT referenced from DatabaseSeeder. The three facility accounts
 * receive the existing GLOBAL "fasilitas" role, which is suitable only for
 * dedicated development/test databases, never for production-like user access.
 * No workflow is published; all loans are drafts and never hold inventory.
 */
class InvManRoleMatrixDemoSeeder extends Seeder
{
    private const PREFIX = '[DEMO-ORG]';

    /** Fixed credentials ONLY for isolated local/testing fixtures. Never usable in staging/production. */
    private const DEMO_PASSWORD = 'password';

    private const UNITS = [
        'tk' => 'TK',
        'sd' => 'SD',
        'smp' => 'SMP',
        'sma' => 'SMA',
        'universitas' => 'Universitas',
    ];

    public function run(): void
    {
        // Weak known credentials must NEVER be provisioned on shared/Internet-accessible environments.
        // Check both runtime and configured environment to resist config/runtime mismatches.
        if (! in_array(app()->environment(), ['local', 'testing'], true)
            || ! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            throw new RuntimeException('Seeder dengan password demo hanya boleh dijalankan di local/testing, bukan staging/production.');
        }
        if (config('security.seed_demo_users') !== true) {
            throw new RuntimeException('Aktifkan SEED_DEMO_USERS=true secara eksplisit untuk seeder matriks peran.');
        }

        foreach ([config('role.admin'), config('role.sarpras'), config('role.fasilitas')] as $role) {
            if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
                throw new RuntimeException("Role {$role} belum tersedia. Jalankan RoleSeeder terlebih dahulu.");
            }
        }

        DB::transaction(function (): void {
            $groups = [
                'fasilitas' => $this->management('Fasilitas'),
                'it' => $this->management('IT'),
                'perpustakaan' => $this->management('Perpustakaan'),
            ];
            $types = [
                'fasilitas' => $this->type('Peralatan Fasilitas'),
                'it' => $this->type('Perangkat IT'),
                'perpustakaan' => $this->type('Koleksi Perpustakaan'),
            ];
            $titles = [];
            foreach ([
                'Kepala Sarpras', 'Staf Fasilitas', 'Supervisor Fasilitas',
                'Manager Fasilitas', 'Teknisi', 'Pengemudi', 'Pengelola IT', 'Pengelola Perpustakaan',
            ] as $title) {
                $record = WorkOrderJobTitle::query()->firstOrCreate(
                    ['name' => self::PREFIX.' '.$title],
                    ['description' => 'Jabatan khusus simulasi matriks organisasi.', 'is_active' => true],
                );
                if (! $record->is_active) {
                    throw new RuntimeException("Jabatan demo {$title} sudah dinonaktifkan; tidak diaktifkan diam-diam.");
                }
                $titles[$title] = $record;
            }

            // The existing admin role is Filament Shield's super-admin role.
            // Do not invent a new role or silently escalate a pre-existing account.
            $admin = $this->account('admin', 'Administrator', null, config('role.admin'));

            // These are three DIFFERENT people with one existing global role.
            // Work Order assignments, not role names, differentiate their jobs.
            $staff = $this->account('staf.fas', 'Staf Fasilitas', null, config('role.fasilitas'));
            $supervisor = $this->account('spv.fas', 'Supervisor Fasilitas', null, config('role.fasilitas'));
            $manager = $this->account('man.fas', 'Manager Fasilitas', null, config('role.fasilitas'));
            $technician = $this->account('teknisi', 'Teknisi Fasilitas', null);
            $itManager = $this->account('it', 'Pengelola Barang IT', null);
            $libraryManager = $this->account('perpus', 'Pengelola Barang Perpustakaan', null);
            $driverUser = $this->account('driver', 'Pengemudi', null);
            $driver = G008M018Driver::query()->firstOrCreate(
                ['user_id' => $driverUser->id],
                ['sim_number' => 'DEMO-ORG-SIM-A-001', 'sim_type' => 'A'],
            );
            if ($driver->sim_number !== 'DEMO-ORG-SIM-A-001') {
                throw new RuntimeException('SIM pengemudi demo sudah diubah; tidak ditimpa.');
            }

            // Do not silently restore memberships intentionally removed by an admin.
            if ($itManager->wasRecentlyCreated) {
                $itManager->itemManagements()->syncWithoutDetaching([$groups['it']->id]);
            }
            if ($libraryManager->wasRecentlyCreated) {
                $libraryManager->itemManagements()->syncWithoutDetaching([$groups['perpustakaan']->id]);
            }

            $this->assignment($staff, $titles['Staf Fasilitas'], null, null, $supervisor);
            $this->assignment($supervisor, $titles['Supervisor Fasilitas'], null, null, $manager);
            $this->assignment($manager, $titles['Manager Fasilitas']);
            // Job assignment grants panel eligibility without the global facility role.
            $this->assignment($driverUser, $titles['Pengemudi']);
            // Technicians report to staff in both the Work Order hierarchy and the field team.
            $this->assignment($technician, $titles['Teknisi'], null, null, $staff);
            $this->teamMember($staff, 'lead', $groups['fasilitas']);
            $this->teamMember($technician, 'technician', $groups['fasilitas'], $staff);
            $this->assignment($itManager, $titles['Pengelola IT'], null, $groups['it']);
            $this->assignment($libraryManager, $titles['Pengelola Perpustakaan'], null, $groups['perpustakaan']);

            foreach (self::UNITS as $slug => $label) {
                // Always create NEW demo units; never attach test actors to real units.
                $unit = G001M001Unit::query()->firstOrCreate(['name' => self::PREFIX.' '.$label]);
                $sarpras = $this->account('sarpras.'.($slug === 'universitas' ? 'univ' : $slug), 'Sarpras '.$label, $unit, config('role.sarpras'));
                $this->assignment($sarpras, $titles['Kepala Sarpras'], $unit);

                $items = [
                    'fasilitas' => $this->item($unit, $slug, 'FAC', 'Kursi Lipat '.$label, 4, $groups['fasilitas'], $types['fasilitas']),
                    'it' => $this->item($unit, $slug, 'IT', 'Laptop '.$label, 2, $groups['it'], $types['it']),
                    'perpustakaan' => $this->item($unit, $slug, 'LIB', 'Buku Referensi '.$label, 3, $groups['perpustakaan'], $types['perpustakaan']),
                ];

                // A mixed IT + library request demonstrates per-reservation
                // approvals and scoped custody without submitting any hold.
                $this->draft($sarpras, $label, $items['it'], $items['perpustakaan']);
                $this->workflowDraft($unit, $slug, $manager, $titles);
            }
        }, 3);

        $this->command?->info('Demo organisasi dibuat: 5 unit, 13 akun sederhana (termasuk admin dan driver), 3 pengelola aset, 15 barang, 5 draf peminjaman, 5 workflow DRAFT.');
        $this->command?->warn('Password demo adalah password untuk seluruh akun. Jangan jalankan di server publik. Role fasilitas bersifat GLOBAL.');
    }

    private function management(string $label): G002M003ItemManagement
    {
        return G002M003ItemManagement::query()->firstOrCreate(
            ['name' => self::PREFIX.' '.$label],
            ['description' => 'Pengelola barang khusus simulasi organisasi; bukan inventaris asli.'],
        );
    }

    private function type(string $label): G002M002ItemType
    {
        return G002M002ItemType::query()->firstOrCreate(
            ['name' => self::PREFIX.' '.$label],
            ['description' => 'Jenis barang demonstrasi, tidak untuk operasional.'],
        );
    }

    private function account(string $username, string $label, ?G001M001Unit $unit, ?string $role = null): User
    {
        $email = $username.'@invman.invalid';
        $existing = User::query()->where('username', $username)->first();

        if ($existing) {
            // An unrelated user must never be hijacked by a predictable username.
            if ($existing->email !== $email
                || ! str_starts_with((string) $existing->name, self::PREFIX.' ')
                || (int) $existing->g001_m001_unit_id !== (int) ($unit?->id ?? 0)
                || ! $existing->is_enabled) {
                throw new RuntimeException("Identitas demo {$username} bertabrakan dengan akun berbeda atau nonaktif.");
            }
            if ($role !== null && ! $existing->hasRole($role)) {
                throw new RuntimeException("Role akun {$username} telah berubah; seeder tidak memberikan eskalasi ulang.");
            }

            return $existing; // Never reset passwords, names, roles or existing units.
        }

        $user = User::query()->create([
            'username' => $username,
            'email' => $email,
            'name' => self::PREFIX.' '.$label,
            'g001_m001_unit_id' => $unit?->id,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'email_verified_at' => now(),
            'is_enabled' => true,
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function assignment(
        User $user,
        WorkOrderJobTitle $title,
        ?G001M001Unit $unit = null,
        ?G002M003ItemManagement $group = null,
        ?User $supervisor = null,
    ): void {
        WorkOrderAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'work_order_job_title_id' => $title->id,
            'g001_m001_unit_id' => $unit?->id,
            'g002_m003_item_management_id' => $group?->id,
        ], [
            'supervisor_user_id' => $supervisor?->id,
            'is_active' => true,
        ]);
    }

    /** The dispatch team is separate from the Work Order approval hierarchy. */
    private function teamMember(
        User $user,
        string $position,
        G002M003ItemManagement $group,
        ?User $lead = null,
    ): void {
        $teamName = self::PREFIX.' Tim Teknis Fasilitas';
        $existing = TicketTeamMember::query()->where('user_id', $user->id)->first();
        if ($existing) {
            if ($existing->team_name !== $teamName
                || $existing->team_type !== 'field'
                || $existing->position !== $position
                || (int) $existing->g002_m003_item_management_id !== (int) $group->id
                || (int) $existing->supervisor_user_id !== (int) ($lead?->id ?? 0)
                || ! $existing->is_active) {
                throw new RuntimeException("Keanggotaan tim {$user->username} telah berubah; seeder tidak menimpanya.");
            }
            return;
        }

        TicketTeamMember::query()->create([
            'user_id' => $user->id,
            'team_name' => $teamName,
            'team_type' => 'field',
            'position' => $position,
            'supervisor_user_id' => $lead?->id,
            'g002_m003_item_management_id' => $group->id,
            'is_active' => true,
        ]);
    }

    private function item(
        G001M001Unit $unit,
        string $slug,
        string $codeSuffix,
        string $label,
        int $quantity,
        G002M003ItemManagement $group,
        G002M002ItemType $type,
    ): G002M007Item {
        $code = 'INVMAN-ORG-'.strtoupper($slug).'-'.$codeSuffix;
        $name = self::PREFIX.' '.$label;
        $existing = G002M007Item::query()->where('code', $code)->first();
        if ($existing && ($existing->name !== $name
            || (int) $existing->g001_m001_unit_id !== (int) $unit->id
            || (int) $existing->g002_m003_item_management_id !== (int) $group->id
            || (int) $existing->g002_m002_item_type_id !== (int) $type->id)) {
            throw new RuntimeException("Kode inventaris demo {$code} sudah dipakai oleh aset berbeda.");
        }

        return G002M007Item::query()->firstOrCreate(['code' => $code], [
            'name' => $name,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $group->id,
            'g002_m002_item_type_id' => $type->id,
            'quantity' => (string) $quantity,
            'is_borrowable' => true,
        ]);
    }

    private function draft(User $borrower, string $label, G002M007Item $it, G002M007Item $library): void
    {
        $name = self::PREFIX.' Pengajuan Campuran '.$label;
        if (G004M008Activity::query()->where('user_id', $borrower->id)->where('name', $name)->exists()) {
            return;
        }
        $start = now()->addDays(30)->startOfDay()->addHours(9);
        app(LoanRequestService::class)->saveDraft($borrower, [
            'name' => $name,
            'description' => 'Simulasi peminjaman barang IT dan Perpustakaan lintas kelompok pengelola.',
            'notes' => 'Belum diajukan; tidak memesan stok inventaris.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'needs' => [
                ['type' => 'item', 'item_id' => $it->id, 'quantity' => 1],
                ['type' => 'item', 'item_id' => $library->id, 'quantity' => 1],
            ],
        ]);
    }

    private function workflowDraft(
        G001M001Unit $unit,
        string $slug,
        User $manager,
        array $titles,
    ): void {
        if (WorkOrderWorkflow::query()->where('g001_m001_unit_id', $unit->id)->exists()) {
            return; // Never replace a published or admin-edited workflow.
        }

        $steps = [
            ['key' => 'diterima_staf', 'label' => 'Diterima Staf Fasilitas',
                'checkpoint' => 'start', 'resolver' => 'job_title',
                'job_title_id' => $titles['Staf Fasilitas']->id],
        ];
        // TK demonstrates two checkpoints; all other units include supervisor.
        if ($slug !== 'tk') {
            $supervisorStep = [
                'key' => 'ditinjau_supervisor', 'label' => 'Ditinjau Supervisor Fasilitas',
                'checkpoint' => 'start', 'resolver' => 'job_title',
                'job_title_id' => $titles['Supervisor Fasilitas']->id,
            ];
            if ($slug === 'universitas') {
                $supervisorStep['requires_estimate'] = true;
            }
            $steps[] = $supervisorStep;
        }
        $steps[] = [
            'key' => 'pengesahan_manager', 'label' => 'Pengesahan Manager Fasilitas',
            'checkpoint' => 'final', 'resolver' => 'job_title',
            'job_title_id' => $titles['Manager Fasilitas']->id,
        ];

        WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'version' => 1,
            'status' => 'draft',
            'created_by' => $manager->id,
            'assign_job_title_id' => $titles['Supervisor Fasilitas']->id,
            'close_job_title_id' => $titles['Manager Fasilitas']->id,
            'dispatch_job_title_id' => $titles['Teknisi']->id,
            'steps' => app(WorkOrderWorkflowService::class)->validateSteps($steps),
        ]);
    }
}
