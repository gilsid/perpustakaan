<x-layouts.app title="Favorit saya">
<div class="container page">
    <header class="page__head"><h1>Rak saya</h1></header>
    <x-account-tabs :tab="$tab" />
    <section class="section section--tight" aria-labelledby="fav">
        <h2 id="fav">Buku favorit <span class="count">{{ $books->count() }}</span></h2>
        @if ($books->isEmpty())
            <div class="empty empty--inline">
                <p>Belum ada buku yang disimpan. Tekan “Simpan ke favorit” di halaman buku untuk menandainya.</p>
                <a class="btn btn--primary" href="{{ route('catalog.index') }}">Jelajahi katalog</a>
            </div>
        @else
            <div class="book-grid">
                @foreach ($books as $b)
                    <div class="fav">
                        <x-book-card :book="$b" />
                        <form method="post" action="{{ route('favorites.destroy', $b) }}">@csrf @method('DELETE')
                            <button class="btn btn--ghost btn--sm" type="submit"><x-icon name="x" />Hapus dari favorit</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
</x-layouts.app>
