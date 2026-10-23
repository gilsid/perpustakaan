<x-layouts.app title="Kategori">
<div class="container page">
    <header class="page__head">
        <h1>Kategori</h1>
        <p class="lead">Pilih satu rak untuk melihat semua bukunya.</p>
    </header>
    @if ($categories->isEmpty())
        <div class="empty"><h2>Belum ada kategori</h2><p>Kategori akan muncul setelah admin menambahkannya.</p></div>
    @else
        <ul class="drawer drawer--wide">
            @foreach ($categories as $c)
                <li><a class="drawer__tab drawer__tab--c{{ $c->tone() }}" href="{{ route('catalog.index', ['kategori' => $c->slug]) }}">
                    <span class="drawer__name">{{ $c->name }}</span><span class="drawer__count">{{ number_format($c->books_count, 0, ',', '.') }} buku</span>
                </a></li>
            @endforeach
        </ul>
    @endif
</div>
</x-layouts.app>
