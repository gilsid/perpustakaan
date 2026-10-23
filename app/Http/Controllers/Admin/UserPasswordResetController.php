<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin membuat kata sandi sementara untuk pengguna (belum ada reset via email). */
class UserPasswordResetController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Ganti kata sandi Anda sendiri lewat halaman Profil.');
        }

        $temporary = Str::password(10, symbols: false);
        $user->update(['password' => $temporary, 'must_change_password' => true]);
        ActivityLog::record('reset_password', 'user', $user->id, "Mereset kata sandi {$user->email}");

        return back()->with('success', "Kata sandi sementara untuk {$user->name}: $temporary — berikan ke pengguna; ia wajib menggantinya saat masuk. Pesan ini hanya tampil sekali.");
    }
}
