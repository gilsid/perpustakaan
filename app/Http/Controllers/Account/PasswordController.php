<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return redirect()->route('account.profile')->with('success', 'Kata sandi diganti.');
    }
}
