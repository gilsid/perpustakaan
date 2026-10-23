<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->string('email')->toString())->first();
        $password = $this->string('password')->toString();
        // Hash::check selalu dijalankan agar waktu respons tidak membocorkan apakah email terdaftar.
        $valid = Hash::check($password, $user?->password ?? $this->dummyHash());

        if (! $user || ! $valid) {
            $this->hitRateLimiters();
            throw ValidationException::withMessages(['form' => 'Email atau kata sandi salah.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['form' => 'Akun ini dinonaktifkan. Hubungi pustakawan untuk mengaktifkannya kembali.']);
        }

        if (Hash::needsRehash($user->password)) {
            $user->update(['password' => $password]);
        }
        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->save();

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        $max = (int) config('library.auth.max_attempts');
        $maxIp = (int) config('library.auth.max_attempts_per_ip');

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $max) && ! RateLimiter::tooManyAttempts($this->ipKey(), $maxIp)) {
            return;
        }

        $seconds = max(RateLimiter::availableIn($this->throttleKey()), RateLimiter::availableIn($this->ipKey()));

        throw ValidationException::withMessages([
            'form' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.max(1, (int) ceil($seconds / 60)).' menit.',
        ]);
    }

    private function hitRateLimiters(): void
    {
        $decay = (int) config('library.auth.lockout_minutes') * 60;
        RateLimiter::hit($this->throttleKey(), $decay);
        RateLimiter::hit($this->ipKey(), $decay);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }

    private function ipKey(): string
    {
        return 'login-ip:'.$this->ip();
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('tidak-dipakai');
    }
}
