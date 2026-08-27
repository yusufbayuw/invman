<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEEDED_USER_PASSWORD', 'password');

        $admin = User::query()->firstOrNew(['username' => 'admin']);
        $admin->fill([
            'name' => $admin->exists ? $admin->name : 'Administrator',
            'email' => $admin->exists ? $admin->email : 'admin@invman.local',
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'g001_m001_unit_id' => null,
        ]);
        $admin->password = Hash::make($password);
        $admin->save();
        $admin->syncRoles([config('role.admin')]);

        $this->createAccount('fasilitas', 'Admin Fasilitas', config('role.fasilitas'), $password);

        foreach ([
            'Daycare, KB & TK' => 'sarpras.daycare',
            'SD' => 'sarpras.sd',
            'SMP' => 'sarpras.smp',
            'SMA' => 'sarpras.sma',
            'TBU' => 'sarpras.tbu',
            'ADM' => 'sarpras.adm',
        ] as $unitName => $username) {
            $unit = G001M001Unit::query()->where('name', $unitName)->firstOrFail();
            $this->createAccount($username, "Sarpras {$unitName}", config('role.sarpras'), $password, $unit->id);
        }
    }

    private function createAccount(
        string $username,
        string $name,
        string $role,
        string $password,
        ?int $unitId = null,
    ): void {
        $user = User::query()->firstOrNew(['username' => $username]);
        $user->fill([
            'name' => $name,
            'email' => "{$username}@invman.local",
            'email_verified_at' => $user->email_verified_at ?? now(),
            'g001_m001_unit_id' => $unitId,
        ]);
        $user->password = Hash::make($password);
        $user->save();
        $user->syncRoles([$role]);
    }
}
