<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function __construct(private readonly Audit $audit) {}

    public function edit(User $user): View
    {
        return view('admin.membership', ['nav' => 'users', 'member' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'string', 'max:30', Rule::unique('users', 'member_number')->ignore($user->id)],
            'membership_expires_at' => ['nullable', 'date'],
        ], [
            'member_number.required' => 'Nomor anggota wajib diisi.',
        ]);

        $user->update($validated);
        $this->audit->membershipUpdated($user, $request->user());

        return redirect()->route('admin.users.index')->with('success', "Kartu anggota {$user->name} diperbarui.");
    }
}
