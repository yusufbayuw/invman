<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Production: akun contoh tidak dibuat atau diubah. Gunakan invman:provision-admin.');

            return;
        }

        if (! config('security.seed_demo_users')) {
            return;
        }

        $password = config('security.seed_demo_password');
        Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $this->createAccount('admin', 'Administrator', config('role.admin'), $password);
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
        // Existing credentials, roles, names and units are never reset by seeders.
        if (User::query()->where('username', $username)->exists()) {
            return;
        }

        $user = User::query()->create([
            'username' => $username,
            'name' => $name,
            'email' => "{$username}@invman.local",
            'email_verified_at' => now(),
            'g001_m001_unit_id' => $unitId,
            'password' => Hash::make($password),
        ]);
        $user->assignRole($role);
    }
}
