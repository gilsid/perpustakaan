<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Menjadikan anggota sebagai admin, atau sebaliknya. */
class UserRoleController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $role = UserRole::from($request->validate(['role' => ['required', Rule::enum(UserRole::class)]])['role']);

        if ($role === $user->role) {
            return back();
        }
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa mengubah peran akun Anda sendiri.');
        }

        $user->update(['role' => $role]);
        ActivityLog::record('change_role', 'user', $user->id, "Mengubah peran {$user->email} menjadi {$role->value}");

        return back()->with('success', $role === UserRole::Admin ? 'Pengguna dijadikan admin.' : 'Pengguna dikembalikan menjadi anggota.');
    }
}
