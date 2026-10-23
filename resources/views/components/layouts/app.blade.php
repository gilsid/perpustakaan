@props(['title'])
@php
    $user = auth()->user();
    $isMember = $user?->isMember();
    $current = fn (string ...$patterns) => request()->is(...$patterns) ? 'aria-current="page"' : '';
    $searchValue = request()->routeIs('catalog.index') && is_string(request()->query('q')) ? request()->query('q') : '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <meta name="description" content="Katalog buku, peminjaman, dan pembaca PDF online.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>
<x-sprite />

<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32">
            <span>{{ config('app.name') }}</span>
        </a>
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-panel">
            <x-icon name="menu" /><span class="sr-only">Menu</span>
        </button>
        <div class="header-panel" id="site-panel">
            <form class="header-search" role="search" action="{{ route('catalog.index') }}" method="get">
                <label class="sr-only" for="header-q">Cari buku</label>
                <x-icon name="search" />
                <input id="header-q" type="search" name="q" placeholder="Cari buku, penulis, ISBN" value="{{ $searchValue }}" maxlength="100">
            </form>
            <nav class="main-nav" aria-label="Utama">
                <a href="{{ route('home') }}" {!! $current('/') !!}>Beranda</a>
                <a href="{{ route('catalog.index') }}" {!! $current('katalog', 'buku/*') !!}>Katalog</a>
                <a href="{{ route('categories.index') }}" {!! $current('kategori') !!}>Kategori</a>
                @if ($isMember)
                    <a href="{{ route('account.loans') }}" {!! $current('pinjaman', 'favorit', 'profil*') !!}>Rak saya</a>
                @endif
            </nav>
            <div class="account">
                @guest
                    <a class="btn btn--ghost" href="{{ route('login') }}">Masuk</a>
                    <a class="btn btn--primary" href="{{ route('register') }}">Daftar</a>
                @endguest
                @auth
                    @if ($user->isAdmin())
                        <a class="btn btn--ghost" href="{{ route('admin.dashboard') }}"><x-icon name="dashboard" />Panel admin</a>
                    @endif
                    <details class="menu">
                        <summary><span class="menu__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span class="menu__name">{{ $user->name }}</span><x-icon name="chevron-down" /></summary>
                        <div class="menu__list">
                            <a href="{{ route('account.profile') }}"><x-icon name="user" />Profil</a>
                            @if ($isMember)
                                <a href="{{ route('account.loans') }}"><x-icon name="book" />Pinjaman saya</a>
                                <a href="{{ route('account.favorites') }}"><x-icon name="bookmark" />Favorit</a>
                            @endif
                            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" />Keluar</button></form>
                        </div>
                    </details>
                @endauth
            </div>
        </div>
    </div>
</header>

<x-flashes />

<main id="main" class="site-main">
    {{ $slot }}
</main>

<footer class="site-footer">
    <div class="container footer-row">
        <p class="footer-brand">{{ config('app.name') }}</p>
        <nav aria-label="Pintasan">
            <a href="{{ route('catalog.index') }}">Katalog</a>
            <a href="{{ route('categories.index') }}">Kategori</a>
            @guest<a href="{{ route('login') }}">Masuk</a><a href="{{ route('register') }}">Daftar</a>@endguest
        </nav>
        <p class="footer-note">Pinjaman berlaku {{ config('library.loan.days') }} hari, maksimal {{ config('library.loan.max_active') }} buku sekaligus.</p>
    </div>
</footer>
<x-confirm-dialog />
</body>
</html>
