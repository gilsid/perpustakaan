<x-layouts.admin title="Eksemplar" nav="copies">
<header class="admin-head"><h1>{{ $copy->exists ? 'Ubah eksemplar' : 'Tambah eksemplar' }}</h1></header>
@if ($errors->any())
    <div class="flashes"><div class="alert alert--error" role="alert"><x-icon name="alert" /><p>{{ $errors->first() }}</p></div></div>
@endif
<form class="card" method="post" action="{{ $copy->exists ? route('admin.copies.update', $copy) : route('admin.copies.store') }}">
    @csrf
    @if ($copy->exists) @method('PUT') @endif
    <div class="field">
        <label for="book_id">Judul buku</label>
        <select id="book_id" name="book_id" required>
            @foreach ($books as $book)
                <option value="{{ $book->id }}" @selected((int) old('book_id', $copy->book_id) === $book->id)>{{ $book->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="code">Barcode / kode eksemplar</label>
        <input id="code" name="code" type="text" required maxlength="40" value="{{ old('code', $copy->code) }}" placeholder="BK-0001-01">
    </div>
    <div class="field">
        <label for="location">Lokasi rak</label>
        <input id="location" name="location" type="text" maxlength="80" value="{{ old('location', $copy->location) }}" placeholder="Rak A">
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $copy->status?->value ?? 'available') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="replacement_cost">Biaya penggantian (Rp)</label>
        <input id="replacement_cost" name="replacement_cost" type="number" min="0" max="100000000" value="{{ old('replacement_cost', $copy->replacement_cost) }}" placeholder="0">
        <p class="hint">Dipakai saat eksemplar dinyatakan rusak atau hilang. Kosong memakai nilai bawaan.</p>
    </div>
    <div class="form-actions">
        <a class="btn btn--ghost" href="{{ route('admin.copies.index') }}">Batal</a>
        <button class="btn btn--primary" type="submit">Simpan</button>
    </div>
</form>
</x-layouts.admin>
