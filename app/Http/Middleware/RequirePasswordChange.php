<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Akun dengan kata sandi sementara (hasil reset admin) wajib menggantinya dulu. */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return redirect(route('account.profile').'#sandi')
                ->with('warning', 'Ganti kata sandi sementara Anda sebelum melanjutkan.');
        }

        return $next($request);
    }
}
