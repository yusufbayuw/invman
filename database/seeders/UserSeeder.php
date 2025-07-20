<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $newUser = \App\Models\User::create([
            'name' => "Yusuf Bayu W",
            'email' => "yusufbayu31@gmail.com",
            'email_verified_at' => now(),
            'username' => "admin",
            'g001_m001_unit_id' => 6, // Assign to the first unit
            'password' => Hash::make('12345678'),
        ]);

        // run 'php artisan shield:generate --all' inside code
        Artisan::call('shield:generate', [
            '--all' => true,
        ]);

        $newUser->assignRole('super_admin');
        $newUser->save();
    }
}
