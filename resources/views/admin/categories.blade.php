<x-layouts.admin title="Kategori" nav="categories">
<header class="admin-head"><h1>Kategori <span class="count">{{ $categories->count() }}</span></h1></header>
<div class="admin-grid admin-grid--cats">
    <section class="card" aria-labelledby="tambah-kat">
        <h2 id="tambah-kat">Tambah kategori</h2>
        <form method="post" action="{{ route('admin.categories.store') }}" novalidate>
            @csrf
            <x-field name="name" label="Nama kategori" :required="true" maxlength="80" />
            <button class="btn btn--primary" type="submit"><x-icon name="plus" />Tambah kategori</button>
        </form>
    </section>

    <section class="card" aria-labelledby="daftar-kat">
        <h2 id="daftar-kat">Daftar kategori</h2>
        @if ($categories->isEmpty())
            <p class="muted">Belum ada kategori.</p>
        @else
            <ul class="cat-list">
                @foreach ($categories as $c)
                    <li>
                        <form class="cat-edit" method="post" action="{{ route('admin.categories.update', $c) }}">
                            @csrf @method('PUT')
                            <label class="sr-only" for="cat-{{ $c->id }}">Nama kategori</label>
                            <input id="cat-{{ $c->id }}" name="name" value="{{ $c->name }}" maxlength="80" required>
                            <button class="btn btn--secondary btn--sm" type="submit">Simpan</button>
                        </form>
                        <span class="muted small">{{ number_format($c->books_count, 0, ',', '.') }} buku · /{{ $c->slug }}</span>
                        <form method="post" action="{{ route('admin.categories.destroy', $c) }}" data-confirm="Hapus kategori “{{ $c->name }}”?" data-confirm-label="Hapus kategori">
                            @csrf @method('DELETE')<button class="btn btn--danger-ghost btn--sm" type="submit" @if ($c->books_count > 0) disabled title="Masih dipakai oleh buku" @endif><x-icon name="trash" /><span class="sr-only">Hapus {{ $c->name }}</span></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
</x-layouts.admin>
