@php
    $isEdit = $book->exists;
    $catOptions = ['' => 'Pilih kategori'] + $categories->pluck('name', 'id')->all();
@endphp
<x-layouts.admin :title="$isEdit ? 'Ubah buku' : 'Tambah buku'" nav="books">
<header class="admin-head">
    <h1>{{ $isEdit ? 'Ubah buku' : 'Tambah buku' }}</h1>
    <a class="btn btn--ghost" href="{{ route('admin.books.index') }}"><x-icon name="arrow-left" />Kembali ke daftar</a>
</header>

@if ($categories->isEmpty())
    <div class="alert alert--warning"><x-icon name="alert" /><p>Belum ada kategori. <a href="{{ route('admin.categories.index') }}">Buat kategori</a> terlebih dulu.</p></div>
@endif

<form class="book-form" method="post" action="{{ $isEdit ? route('admin.books.update', $book) : route('admin.books.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="book-form__main card">
        <h2>Data buku</h2>
        <x-field name="title" label="Judul" :value="$book->title" :required="true" maxlength="255" />
        <x-field name="author" label="Penulis" :value="$book->author" :required="true" maxlength="255" hint="Pisahkan beberapa penulis dengan koma." />
        <div class="grid-2">
            <x-field name="category_id" label="Kategori" type="select" :options="$catOptions" :value="$book->category_id" :required="true" />
            <x-field name="call_number" label="Nomor panggil" :value="$book->call_number" maxlength="40" placeholder="mis. 005.1 HUN p" />
        </div>
        <div class="grid-2">
            <x-field name="isbn" label="ISBN" :value="$book->isbn" maxlength="20" inputmode="numeric" />
            <x-field name="publisher" label="Penerbit" :value="$book->publisher" maxlength="160" />
        </div>
        <div class="grid-3">
            <x-field name="published_year" label="Tahun terbit" type="number" :value="$book->published_year" min="1000" max="2200" />
            <x-field name="pages" label="Jumlah halaman" type="number" :value="$book->pages" min="1" />
            <div class="field">
                <label for="f-language">Bahasa</label>
                <input id="f-language" name="language" list="lang-list" value="{{ old('language', $book->language) }}" maxlength="40">
                <datalist id="lang-list">@foreach ($languages as $l)<option value="{{ $l }}">@endforeach</datalist>
            </div>
        </div>
        <x-field name="description" label="Sinopsis" type="textarea" :rows="7" :value="$book->description" maxlength="5000" />
    </div>

    <aside class="book-form__side">
        <div class="card">
            <h2>Sampul</h2>
            <div class="upload-preview" data-cover-preview>
                @if ($isEdit)<x-cover :book="$book" :decorative="false" />@else<span class="cover cover--gen cover--c6"><span class="cover__title">Pratinjau sampul</span></span>@endif
            </div>
            <div @class(['field', 'has-error' => $errors->has('cover')])>
                <label for="f-cover">{{ $isEdit && $book->cover_path ? 'Ganti sampul' : 'Unggah sampul' }}</label>
                <p class="hint" id="hint-cover">JPG, PNG, atau WebP, maksimal {{ $limits['cover'] }} MB. Tanpa sampul, sistem membuat sampul bertipografi otomatis.</p>
                <input id="f-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-describedby="hint-cover">
                @error('cover')<p class="error"><x-icon name="alert" /><span>{{ $message }}</span></p>@enderror
            </div>
            @if ($isEdit && $book->cover_path)<label class="check"><input type="checkbox" name="remove_cover" value="1"><span>Hapus sampul saat ini</span></label>@endif
        </div>

        <div class="card">
            <h2>Berkas buku (PDF)</h2>
            @if ($isEdit && $book->pdf_path)<p class="small"><span class="badge badge--ok">PDF terpasang</span> Anggota bisa meminjam buku ini.</p>@endif
            <div @class(['field', 'has-error' => $errors->has('pdf')])>
                <label for="f-pdf">{{ $isEdit && $book->pdf_path ? 'Ganti PDF' : 'Unggah PDF' }}</label>
                <p class="hint" id="hint-pdf">Maksimal {{ $limits['pdf'] }} MB. Berkas disimpan di luar folder publik dan hanya bisa dibuka anggota yang sedang meminjam. Tanpa PDF, buku hanya tampil di katalog.</p>
                <input id="f-pdf" type="file" name="pdf" accept="application/pdf" aria-describedby="hint-pdf">
                @error('pdf')<p class="error"><x-icon name="alert" /><span>{{ $message }}</span></p>@enderror
            </div>
            @if ($isEdit && $book->pdf_path)<label class="check"><input type="checkbox" name="remove_pdf" value="1"><span>Hapus PDF saat ini</span></label>@endif
        </div>

        <div class="form-actions">
            <button class="btn btn--primary btn--lg" type="submit">{{ $isEdit ? 'Simpan perubahan' : 'Tambah buku' }}</button>
            <a class="btn btn--ghost btn--lg" href="{{ route('admin.books.index') }}">Batal</a>
        </div>
    </aside>
</form>
</x-layouts.admin>
