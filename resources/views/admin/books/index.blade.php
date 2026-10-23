<x-layouts.admin title="Buku" nav="books">
<header class="admin-head">
    <h1>Buku <span class="count">{{ number_format($books->total(), 0, ',', '.') }}</span></h1>
    <a class="btn btn--primary" href="{{ route('admin.books.create') }}"><x-icon name="plus" />Tambah buku</a>
</header>

<form class="filter-row" method="get" action="{{ route('admin.books.index') }}" role="search">
    <label class="sr-only" for="aq">Cari buku</label>
    <input id="aq" type="search" name="q" value="{{ $filters->q }}" placeholder="Cari judul, penulis, atau ISBN">
    <label class="sr-only" for="akat">Kategori</label>
    <select id="akat" name="kategori">
        <option value="">Semua kategori</option>
        @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($filters->category === $c->slug)>{{ $c->name }}</option>@endforeach
    </select>
    <button class="btn btn--secondary" type="submit">Cari</button>
</form>

@if ($books->isEmpty())
    <div class="empty"><h2>Tidak ada buku</h2><p>{{ $filters->q !== '' || $filters->category !== '' ? 'Tidak ada buku yang cocok dengan pencarian ini.' : 'Mulai dengan menambahkan buku pertama.' }}</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Buku</th><th scope="col">Kategori</th><th scope="col">Tahun</th><th scope="col">Format</th><th scope="col">Rating</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @foreach ($books as $b)
                <tr>
                    <td>
                        <div class="media">
                            <div class="media__cover"><x-cover :book="$b" /></div>
                            <div><a class="media__title" href="{{ route('admin.books.edit', $b) }}">{{ $b->title }}</a><br><span class="muted small">{{ $b->author }}</span></div>
                        </div>
                    </td>
                    <td>{{ $b->category->name ?? '–' }}</td>
                    <td>{{ $b->published_year ?? '–' }}</td>
                    <td>@if ($b->has_pdf)<span class="badge badge--ok">PDF</span>@else<span class="badge badge--muted">Katalog</span>@endif</td>
                    <td>{{ $b->review_count > 0 ? number_format($b->rating, 1, ',', '.').' ('.$b->review_count.')' : '–' }}</td>
                    <td class="actions">
                        <a class="btn btn--ghost btn--sm" href="{{ route('books.show', $b) }}">Lihat</a>
                        <a class="btn btn--secondary btn--sm" href="{{ route('admin.books.edit', $b) }}"><x-icon name="edit" />Ubah</a>
                        <form method="post" action="{{ route('admin.books.destroy', $b) }}" data-confirm="Hapus “{{ $b->title }}”? Berkas PDF, sampul, ulasan, dan riwayat pinjamnya ikut terhapus. Tindakan ini tidak bisa dibatalkan." data-confirm-label="Hapus buku">
                            @csrf @method('DELETE')<button class="btn btn--danger-ghost btn--sm" type="submit"><x-icon name="trash" /><span class="sr-only">Hapus {{ $b->title }}</span></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $books->links() }}
@endif
</x-layouts.admin>
