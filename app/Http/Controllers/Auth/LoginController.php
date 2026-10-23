<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        // ?next= hanya menerima path internal (anti open-redirect).
        $next = $request->query('next');
        if (is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\')) {
            redirect()->setIntendedUrl(url($next));
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        if ($user->must_change_password) {
            return redirect(route('account.profile').'#sandi')
                ->with('warning', 'Anda masuk dengan kata sandi sementara. Ganti kata sandi sekarang.');
        }

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('home'))
            ->with('success', 'Selamat datang kembali, '.$user->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda sudah keluar.');
    }
}
