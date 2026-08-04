<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ─────────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@digestedu.com'],
            [
                'name'     => 'Admin DigestEdu',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'is_active' => true,
            ],
        );

        // ── Test User ─────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'budi@digestedu.com'],
            [
                'name'     => 'Budi Santoso',
                'password' => Hash::make('password'),
                'role'     => 'user',
                'is_active' => true,
            ],
        );
    }
}
