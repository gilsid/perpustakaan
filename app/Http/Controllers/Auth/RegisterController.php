<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'role' => UserRole::Member,
        ]);

        // Daftar sekaligus masuk: catat waktu masuk pertama seperti pada login biasa.
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();
        ActivityLog::record('register', 'user', $user->id, "Anggota baru mendaftar: {$user->email}", $user);

        return redirect()->route('home')->with('success', "Akun dibuat. Selamat datang, {$user->name}!");
    }
}
