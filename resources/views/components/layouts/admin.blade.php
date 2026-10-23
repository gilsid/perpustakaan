@props(['title', 'nav' => 'dashboard'])
@php
    $items = [
        'dashboard' => ['admin.dashboard', 'dashboard', 'Dasbor'],
        'books' => ['admin.books.index', 'books', 'Buku'],
        'categories' => ['admin.categories.index', 'tag', 'Kategori'],
        'loans' => ['admin.loans.index', 'clock', 'Peminjaman'],
        'reviews' => ['admin.reviews.index', 'star', 'Ulasan'],
        'users' => ['admin.users.index', 'users', 'Pengguna'],
        'logs' => ['admin.logs.index', 'list', 'Log aktivitas'],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · Panel admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Lewati ke konten</a>
<x-sprite />
<div class="admin">
    <aside class="admin__side" id="admin-side">
        <a class="brand brand--light" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32"><span>Panel admin</span>
        </a>
        <nav class="admin__nav" aria-label="Admin">
            @foreach ($items as $key => [$routeName, $ic, $label])
                <a href="{{ route($routeName) }}" @if ($nav === $key) aria-current="page" @endif><x-icon :name="$ic" />{{ $label }}</a>
            @endforeach
        </nav>
        <div class="admin__foot">
            <a href="{{ route('home') }}"><x-icon name="external" />Lihat situs</a>
            <p class="admin__who">{{ auth()->user()->name }}</p>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" />Keluar</button></form>
        </div>
    </aside>
    <div class="admin__main">
        <div class="admin__bar">
            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="admin-side"><x-icon name="menu" /><span class="sr-only">Menu admin</span></button>
            <span class="admin__bar-title">{{ $title }}</span>
        </div>
        <x-flashes />
        <main id="main" class="admin__content">
            {{ $slot }}
        </main>
    </div>
</div>
<x-confirm-dialog />
</body>
</html>
