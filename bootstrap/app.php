<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => auth()->user()?->isAdmin() ? route('admin.dashboard') : route('home'));

        $middleware->web(append: [
            EnsureUserIsActive::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'password.fresh' => RequirePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Berkas melebihi post_max_size: kembali ke form dengan pesan yang jelas, bukan halaman 413 kosong.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            return back()->with('error', 'Ukuran unggahan melebihi batas server (post_max_size = '.ini_get('post_max_size').'). Naikkan batas di php.ini atau kecilkan berkas.');
        });
    })->create();
