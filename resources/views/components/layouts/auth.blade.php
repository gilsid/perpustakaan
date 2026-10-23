@props(['title'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Lewati ke konten</a>
<x-sprite />
<main id="main" class="auth">
    <aside class="auth__aside">
        <a class="brand brand--light" href="{{ route('home') }}">
            <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32"><span>{{ config('app.name') }}</span>
        </a>
        <div class="auth__pitch">
            <p class="auth__lead">Satu akun untuk meminjam dan membaca buku dari mana saja.</p>
            <ul class="auth__points">
                <li><x-icon name="book" />Pinjam hingga {{ config('library.loan.max_active') }} buku sekaligus, masing-masing {{ config('library.loan.days') }} hari</li>
                <li><x-icon name="bookmark" />Simpan buku incaran di rak favorit</li>
                <li><x-icon name="star" />Tulis ulasan setelah selesai membaca</li>
            </ul>
        </div>
    </aside>
    <div class="auth__panel">
        <x-flashes />
        {{ $slot }}
    </div>
</main>
</body>
</html>
