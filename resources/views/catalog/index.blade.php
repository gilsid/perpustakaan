@php
    $params = $filters->toQuery();
    $chips = [];
    if ($filters->q !== '') { $chips[] = ['Kata kunci: '.$filters->q, ['q']]; }
    if ($currentCategory) { $chips[] = ['Kategori: '.$currentCategory->name, ['kategori']]; }
    if ($filters->yearFrom || $filters->yearTo) { $chips[] = ['Tahun: '.($filters->yearFrom ?: '…').'–'.($filters->yearTo ?: '…'), ['dari', 'sampai']]; }
    if ($filters->language !== '') { $chips[] = ['Bahasa: '.$filters->language, ['bahasa']]; }
    if ($filters->digital) { $chips[] = ['Bisa dibaca online', ['digital']]; }
    $heading = $filters->q !== '' ? 'Hasil untuk “'.$filters->q.'”' : ($currentCategory->name ?? 'Katalog');
@endphp
<x-layouts.app :title="$heading">
<div class="container page">
    <header class="page__head">
        <h1>{{ $heading }}</h1>
        <p class="lead" aria-live="polite">{{ number_format($books->total(), 0, ',', '.') }} buku ditemukan{{ $books->total() > 0 ? ', menampilkan '.number_format($books->firstItem(), 0, ',', '.').'–'.number_format($books->lastItem(), 0, ',', '.') : '' }}.</p>
    </header>

    <form class="catalog" method="get" action="{{ route('catalog.index') }}" data-autosubmit>
        @if ($filters->q !== '')<input type="hidden" name="q" value="{{ $filters->q }}">@endif

        <details class="filters" data-open-wide>
            <summary class="filters__toggle"><x-icon name="filter" />Saring hasil</summary>
            <div class="filters__body">
                <fieldset>
                    <legend>Kategori</legend>
                    <ul class="filter-list">
                        <li><label><input type="radio" name="kategori" value="" @checked($filters->category === '')><span>Semua kategori</span></label></li>
                        @foreach ($categoryCounts as $c)
                            <li><label><input type="radio" name="kategori" value="{{ $c->slug }}" @checked($filters->category === $c->slug)><span>{{ $c->name }}</span><small>{{ number_format($c->books_count, 0, ',', '.') }}</small></label></li>
                        @endforeach
                    </ul>
                </fieldset>
                <fieldset>
                    <legend>Tahun terbit</legend>
                    <div class="range">
                        <label class="sr-only" for="f-dari">Dari tahun</label><input id="f-dari" type="number" name="dari" min="1000" max="2200" placeholder="Awal" value="{{ $filters->yearFrom }}">
                        <span aria-hidden="true">–</span>
                        <label class="sr-only" for="f-sampai">Sampai tahun</label><input id="f-sampai" type="number" name="sampai" min="1000" max="2200" placeholder="Akhir" value="{{ $filters->yearTo }}">
                    </div>
                </fieldset>
                @if ($languages->isNotEmpty())
                    <fieldset>
                        <legend>Bahasa</legend>
                        <label class="sr-only" for="f-bahasa">Bahasa</label>
                        <select id="f-bahasa" name="bahasa">
                            <option value="">Semua bahasa</option>
                            @foreach ($languages as $l)<option value="{{ $l }}" @selected($filters->language === $l)>{{ $l }}</option>@endforeach
                        </select>
                    </fieldset>
                @endif
                <fieldset>
                    <legend>Format</legend>
                    <label class="check"><input type="checkbox" name="digital" value="1" @checked($filters->digital)><span>Hanya yang bisa dibaca online</span></label>
                </fieldset>
                <div class="filters__actions">
                    <button class="btn btn--primary" type="submit">Terapkan</button>
                    <a class="btn btn--ghost" href="{{ route('catalog.index', ['q' => $filters->q]) }}">Atur ulang</a>
                </div>
            </div>
        </details>

        <div class="catalog__results">
            <div class="toolbar">
                @if ($chips)
                    <ul class="chips" aria-label="Filter aktif">
                        @foreach ($chips as [$label, $keys])
                            <li><a class="chip" href="{{ route('catalog.index', array_diff_key($params, array_flip($keys))) }}" aria-label="Hapus filter {{ $label }}">{{ $label }}<x-icon name="x" /></a></li>
                        @endforeach
                    </ul>
                @else
                    <span></span>
                @endif
                <div class="sort">
                    <label for="f-urut">Urutkan</label>
                    <select id="f-urut" name="urut">
                        @foreach ($sorts as $s)<option value="{{ $s->value }}" @selected($filters->sort === $s)>{{ $s->label() }}</option>@endforeach
                    </select>
                    <noscript><button class="btn btn--sm" type="submit">Terapkan</button></noscript>
                </div>
            </div>

            @if ($books->isEmpty())
                <div class="empty">
                    <x-icon name="search" class="empty__icon" />
                    <h2>Tidak ada buku yang cocok</h2>
                    <p>Coba kata kunci yang lebih pendek, periksa ejaan, atau hapus beberapa filter.</p>
                    @if ($chips)<a class="btn btn--primary" href="{{ route('catalog.index') }}">Hapus semua filter</a>@endif
                </div>
            @else
                <div class="book-grid book-grid--catalog">
                    @foreach ($books as $b)<x-book-card :book="$b" />@endforeach
                </div>
                {{ $books->links() }}
            @endif
        </div>
    </form>
</div>
</x-layouts.app>
