<x-layouts.admin title="Eksemplar" nav="copies">
<header class="admin-head"><h1>Eksemplar <span class="count">{{ number_format($copies->total(), 0, ',', '.') }}</span></h1></header>
<div class="filter-row filter-row--split">
    <a class="btn btn--primary" href="{{ route('admin.copies.create') }}">Tambah eksemplar</a>
    <form method="get" action="{{ route('admin.copies.index') }}" role="search">
        <label class="sr-only" for="cq">Cari eksemplar</label>
        <input id="cq" type="search" name="q" value="{{ $q }}" placeholder="Cari barcode atau judul">
        <button class="btn btn--secondary" type="submit">Cari</button>
    </form>
</div>
@if ($copies->isEmpty())
    <div class="empty"><h2>Belum ada eksemplar</h2><p>Tambahkan eksemplar fisik untuk mulai melayani sirkulasi.</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Barcode</th><th scope="col">Judul buku</th><th scope="col">Lokasi</th><th scope="col">Status</th><th scope="col">Biaya ganti</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @foreach ($copies as $copy)
                <tr>
                    <td><strong>{{ $copy->code }}</strong></td>
                    <td><a href="{{ route('books.show', $copy->book) }}">{{ $copy->book->title }}</a></td>
                    <td>{{ $copy->location ?? '–' }}</td>
                    <td><span class="badge {{ $copy->status->badgeClass() }}">{{ $copy->status->label() }}</span></td>
                    <td>{{ $copy->replacement_cost > 0 ? \App\Support\Rupiah::format($copy->replacement_cost) : '–' }}</td>
                    <td class="actions">
                        <a class="act-btn" href="{{ route('admin.copies.edit', $copy) }}"><x-icon name="edit" />Ubah</a>
                        <form method="post" action="{{ route('admin.copies.destroy', $copy) }}" data-confirm="Hapus eksemplar {{ $copy->code }}?" data-confirm-label="Hapus">
                            @csrf @method('DELETE')
                            <button class="act-btn act-btn--danger act-btn--icon" type="submit" title="Hapus {{ $copy->code }}"><x-icon name="trash" /><span class="sr-only">Hapus {{ $copy->code }}</span></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $copies->links() }}
@endif
</x-layouts.admin>
