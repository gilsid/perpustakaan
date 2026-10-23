<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Mengaktifkan / menonaktifkan akun. */
class UserStatusController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
        }

        $activate = ! $user->is_active;
        if (! $activate && $user->role === UserRole::Admin && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Harus ada minimal satu admin aktif.');
        }

        $user->update(['is_active' => $activate]);
        ActivityLog::record($activate ? 'activate_user' : 'deactivate_user', 'user', $user->id, ($activate ? 'Mengaktifkan' : 'Menonaktifkan')." akun {$user->email}");

        return back()->with('success', $activate ? 'Akun diaktifkan.' : 'Akun dinonaktifkan. Pengguna otomatis keluar dari semua sesi.');
    }
}
