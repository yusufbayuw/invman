<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class WorkOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        // NEVER provision sample accounts, default credentials or approval rules in production.
        if (app()->environment('production') || config('app.env') === 'production'
            || ! config('security.seed_demo_users')) {
            return;
        }

        $password = config('security.seed_demo_password');
        Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $this->call(WorkOrderJobTitleSeeder::class);
        $titles = WorkOrderJobTitle::query()->pluck('id', 'name');
        $groups = G002M003ItemManagement::query()->pluck('id', 'name');

        $staff = $this->demoUser('wo.demo.fasilitas.staff', 'Demo Staf Fasilitas', 'fasilitas', $password);
        $supervisor = $this->demoUser('wo.demo.fasilitas.spv', 'Demo Supervisor Fasilitas', 'fasilitas', $password);
        $manager = $this->demoUser('wo.demo.fasilitas.manager', 'Demo Manager Fasilitas', 'fasilitas', $password);
        $itStaff = $this->demoUser('wo.demo.it.staff', 'Demo Staf IT', null, $password);
        $itSpv = $this->demoUser('wo.demo.it.spv', 'Demo Supervisor IT', null, $password);
        $libraryStaff = $this->demoUser('wo.demo.perpustakaan.staff', 'Demo Staf Perpustakaan', null, $password);
        $librarySpv = $this->demoUser('wo.demo.perpustakaan.spv', 'Demo Supervisor Perpustakaan', null, $password);
        $lead = $this->demoUser('wo.demo.teknisi.koordinator', 'Demo Koordinator Teknisi', null, $password);
        $technician = $this->demoUser('wo.demo.teknisi.petugas', 'Demo Teknisi', null, $password);

        $this->position($staff, $titles['Staf Fasilitas']);
        $this->position($supervisor, $titles['Supervisor Fasilitas'], null, null, $manager?->id);
        $this->position($manager, $titles['Manager Fasilitas']);
        $this->position($itStaff, $titles['Staf IT'], null, $groups['IT'] ?? null, $itSpv?->id);
        $this->position($itSpv, $titles['Supervisor IT'], null, $groups['IT'] ?? null);
        $this->position($libraryStaff, $titles['Staf Perpustakaan'], null, $groups['Perpustakaan'] ?? null, $librarySpv?->id);
        $this->position($librarySpv, $titles['Supervisor Perpustakaan'], null, $groups['Perpustakaan'] ?? null);
        $this->position($lead, $titles['Koordinator Teknisi'], null, $groups['Fasilitas'] ?? null);
        $this->position($technician, $titles['Teknisi'], null, $groups['Fasilitas'] ?? null, $lead?->id);

        // Grant asset membership only to *newly provisioned* demo identities,
        // never to a real account that happens to share a username.
        foreach ([[$itStaff, 'IT'], [$itSpv, 'IT'], [$libraryStaff, 'Perpustakaan'], [$librarySpv, 'Perpustakaan']] as [$user, $group]) {
            if ($user && isset($groups[$group])) {
                $user->itemManagements()->syncWithoutDetaching([(int) $groups[$group]]);
            }
        }

        if ($lead && $technician && isset($groups['Fasilitas'])) {
            TicketTeamMember::query()->firstOrCreate(['user_id' => $lead->id], [
                'team_name' => 'Tim Fasilitas Demo', 'team_type' => 'field', 'position' => 'lead',
                'g002_m003_item_management_id' => (int) $groups['Fasilitas'],
                'is_active' => true,
            ]);
            TicketTeamMember::query()->firstOrCreate(['user_id' => $technician->id], [
                'team_name' => 'Tim Fasilitas Demo', 'team_type' => 'field', 'position' => 'technician',
                'g002_m003_item_management_id' => (int) $groups['Fasilitas'],
                'supervisor_user_id' => $lead->id, 'is_active' => true,
            ]);
        }

        // Illustrative unit organization. Keep existing Sarpras accounts and credentials intact.
        foreach ([
            'Daycare, KB & TK' => ['daycare', 'sarpras.daycare'],
            'SD' => ['sd', 'sarpras.sd'],
            'SMP' => ['smp', 'sarpras.smp'],
            'SMA' => ['sma', 'sarpras.sma'],
            'TBU' => ['tbu', 'sarpras.tbu'],
            'ADM' => ['adm', 'sarpras.adm'],
        ] as $unitName => [$slug, $sarprasUsername]) {
            $unit = G001M001Unit::query()->where('name', $unitName)->first();
            if (! $unit) {
                continue;
            }
            $head = $this->demoUser(
                'wo.demo.unit.'.$slug.'.atasan', 'Demo Atasan Unit '.$unitName,
                null, $password, $unit->id,
            );
            $this->position($head, $titles['Atasan Unit'], $unit->id);
            $sarpras = User::query()->where('username', $sarprasUsername)
                ->where('email', $sarprasUsername.'@invman.local')->first();
            $this->position($sarpras, $titles['Kepala Sarpras'], $unit->id, null, $head?->id);
        }

        // An example remains a DRAFT. Only an administrator may publish it
        // after checking the actual organization and coverage of approvers.
        if ($manager && $supervisor && $staff && ! WorkOrderWorkflow::query()->whereNull('g001_m001_unit_id')->exists()) {
            WorkOrderWorkflow::query()->create([
                'g001_m001_unit_id' => null,
                'version' => 1,
                'status' => 'draft',
                'created_by' => $manager->id,
                'assign_job_title_id' => $titles['Supervisor Fasilitas'],
                'close_job_title_id' => $titles['Manager Fasilitas'],
                'steps' => [
                    ['key' => 'diketahui_atasan', 'label' => 'Diketahui Atasan Pelapor',
                        'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
                    ['key' => 'diterima_fasilitas', 'label' => 'Diterima Staf Fasilitas',
                        'checkpoint' => 'start', 'resolver' => 'job_title',
                        'job_title_id' => $titles['Staf Fasilitas']],
                    ['key' => 'tinjauan_supervisor', 'label' => 'Ditinjau Supervisor Fasilitas',
                        'checkpoint' => 'start', 'resolver' => 'job_title',
                        'job_title_id' => $titles['Supervisor Fasilitas']],
                    ['key' => 'pengesahan_manager', 'label' => 'Pengesahan Manager Fasilitas',
                        'checkpoint' => 'final', 'resolver' => 'job_title',
                        'job_title_id' => $titles['Manager Fasilitas']],
                ],
            ]);
        }
    }

    private function demoUser(string $username, string $name, ?string $role, string $password, ?int $unitId = null): ?User
    {
        $existing = User::query()->where('username', $username)->first();
        if ($existing) {
            // Never alter an existing account with this username unless it has our
            // unmistakable demo email; even demo account credentials are immutable here.
            return $existing->email === $username.'@invman.local' ? $existing : null;
        }

        $user = User::query()->create([
            'username' => $username, 'name' => $name,
            'email' => $username.'@invman.local', 'email_verified_at' => now(),
            'password' => Hash::make($password), 'g001_m001_unit_id' => $unitId,
        ]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function position(?User $user, int $titleId, ?int $unitId = null, ?int $managementId = null, ?int $supervisorId = null): void
    {
        if (! $user) {
            return;
        }
        WorkOrderAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'work_order_job_title_id' => $titleId,
            'g001_m001_unit_id' => $unitId,
            'g002_m003_item_management_id' => $managementId,
        ], [
            'supervisor_user_id' => $supervisorId, 'is_active' => true,
        ]);
    }
}
