<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class AdminProvisioner
{
    public function provision(string $username, string $email, string $password): User
    {
        Validator::make(compact('username', 'email', 'password'), [
            'username' => ['required', 'alpha_dash', 'min:3', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $role = Role::query()->where('name', config('role.admin'))->where('guard_name', 'web')->first();
        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Jalankan php artisan db:seed --class=RoleSeeder terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($username, $email, $password, $role): User {
            // Refuse existing usernames/emails (even if they were created between
            // validation and this write); do not rotate passwords through seeders.
            if (User::query()->where('username', $username)->orWhere('email', $email)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['username' => 'Username atau email sudah digunakan.']);
            }

            $user = User::query()->create([
                'username' => $username,
                'email' => $email,
                'name' => 'Administrator',
                'email_verified_at' => now(),
                'password' => $password,
            ]);

            $user->assignRole($role);

            return $user;
        });
    }
}
