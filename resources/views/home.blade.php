<x-layouts.app title="Beranda">
<section class="hero">
    <div class="container hero__grid">
        <div class="hero__text">
            <h1>Pinjam dan baca buku dari mana saja</h1>
            <p class="lead">{{ number_format($stats['titles'], 0, ',', '.') }} judul tercatat di katalog, {{ number_format($stats['digital'], 0, ',', '.') }} di antaranya bisa dipinjam dan dibaca langsung di layar.</p>
            <form class="hero-search" role="search" action="{{ route('catalog.index') }}" method="get">
                <label class="sr-only" for="hero-q">Cari buku</label>
                <input id="hero-q" type="search" name="q" placeholder="Judul, penulis, atau ISBN" maxlength="100">
                <button class="btn btn--primary btn--lg" type="submit"><x-icon name="search" />Cari buku</button>
            </form>
            @if ($categories->isNotEmpty())
                <p class="hero__cats">
                    <span>Langsung ke:</span>
                    @foreach ($categories->take(5) as $c)
                        <a href="{{ route('catalog.index', ['kategori' => $c->slug]) }}">{{ $c->name }}</a>
                    @endforeach
                </p>
            @endif
        </div>

        @if ($newest->isNotEmpty())
            <div class="hero__shelf">
                <ul class="shelf" aria-label="Buku yang baru ditambahkan">
                    @foreach ($newest->take(5) as $i => $b)
                        <li class="shelf__item shelf__item--{{ $i }}">
                            <a href="{{ route('books.show', $b) }}" title="{{ $b->title }}">
                                <x-cover :book="$b" :decorative="false" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="shelf__caption">Baru ditambahkan · <a href="{{ route('catalog.index', ['urut' => 'terbaru']) }}">Lihat semua</a></p>
            </div>
        @endif
    </div>
</section>

@if ($newest->isEmpty())
    <section class="container section">
        <div class="empty">
            <x-icon name="books" class="empty__icon" />
            <h2>Katalog masih kosong</h2>
            <p>Belum ada buku yang ditambahkan.</p>
            @if (auth()->user()?->isAdmin())
                <a class="btn btn--primary" href="{{ route('admin.books.create') }}"><x-icon name="plus" />Tambah buku pertama</a>
            @endif
        </div>
    </section>
@endif

@if ($topRated->isNotEmpty())
    <section class="container section">
        <header class="section__head">
            <h2>Disukai pembaca</h2>
            <a href="{{ route('catalog.index', ['urut' => 'rating']) }}">Urutkan semua berdasarkan rating</a>
        </header>
        <div class="book-grid">
            @foreach ($topRated as $b)<x-book-card :book="$b" />@endforeach
        </div>
    </section>
@endif

@if ($categories->isNotEmpty())
    <section class="container section">
        <header class="section__head">
            <h2>Telusuri berdasarkan kategori</h2>
            <a href="{{ route('categories.index') }}">Semua kategori</a>
        </header>
        <ul class="drawer">
            @foreach ($categories as $c)
                <li><a class="drawer__tab drawer__tab--c{{ $c->tone() }}" href="{{ route('catalog.index', ['kategori' => $c->slug]) }}">
                    <span class="drawer__name">{{ $c->name }}</span><span class="drawer__count">{{ number_format($c->books_count, 0, ',', '.') }} buku</span>
                </a></li>
            @endforeach
        </ul>
    </section>
@endif

@guest
    <section class="container section">
        <header class="section__head"><h2>Cara meminjam</h2></header>
        <ol class="steps">
            <li><h3>Buat akun</h3><p>Daftar gratis dengan email. Tidak perlu kartu anggota.</p></li>
            <li><h3>Pinjam bukunya</h3><p>Pilih buku bertanda “Bisa dibaca”, lalu tekan Pinjam. Masa pinjam {{ config('library.loan.days') }} hari dan bisa diperpanjang.</p></li>
            <li><h3>Baca di layar</h3><p>Buka pembaca PDF langsung di peramban, di ponsel maupun komputer.</p></li>
        </ol>
        <p><a class="btn btn--primary" href="{{ route('register') }}">Buat akun</a></p>
    </section>
@endguest
</x-layouts.app>
