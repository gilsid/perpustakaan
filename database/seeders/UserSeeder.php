<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Akun contoh. GANTI atau hapus sebelum dipakai sungguhan. */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => 'admin123', 'role' => UserRole::Admin],
        );
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Regular User', 'password' => 'password123', 'role' => UserRole::Member],
        );
    }
}
