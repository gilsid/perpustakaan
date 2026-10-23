<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('library:create-admin {name? : Nama lengkap} {email? : Email} {password? : Kata sandi (min. 8 karakter)}')]
#[Description('Membuat akun admin, atau menjadikan akun yang sudah ada sebagai admin')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $name = $this->argument('name') ?? text('Nama lengkap', required: true);
        $email = mb_strtolower((string) ($this->argument('email') ?? text('Email', required: true)));
        $password = $this->argument('password') ?? password('Kata sandi (min. 8 karakter)', required: true);

        $min = (int) config('library.auth.min_password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < $min) {
            $this->components->error("Email tidak valid atau kata sandi terlalu pendek (minimal $min karakter).");

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $existing->update(['role' => UserRole::Admin, 'password' => $password, 'is_active' => true, 'must_change_password' => false]);
            $this->components->info("Akun $email diperbarui menjadi admin.");
        } else {
            User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => UserRole::Admin]);
            $this->components->info("Admin dibuat: $email");
        }

        return self::SUCCESS;
    }
}
