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
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => 'admin123', 'role' => UserRole::Admin],
        );
        if (! $admin->member_number) {
            $admin->update(['member_number' => User::memberNumberFor($admin->id)]);
        }

        $member = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Regular User', 'password' => 'password123', 'role' => UserRole::Member],
        );
        if (! $member->member_number) {
            $member->update(['member_number' => User::memberNumberFor($member->id)]);
        }
    }
}
