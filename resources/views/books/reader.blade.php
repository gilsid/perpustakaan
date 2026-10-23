<x-layouts.reader :title="'Membaca: '.$book->title">
<div class="reader">
    <header class="reader__bar">
        <a class="btn btn--ghost reader__back" href="{{ route('books.show', $book) }}"><x-icon name="arrow-left" />Kembali</a>
        <div class="reader__title">
            <h1>{{ $book->title }}</h1>
            <p>{{ $book->author }}@if ($loan) · Pinjaman berakhir {{ $loan->due_at->translatedFormat('j M Y') }} ({{ $loan->days_left_label }})@else · Mode admin @endif</p>
        </div>
        <a class="btn btn--ghost" href="{{ route('reader.file', $book) }}" target="_blank" rel="noopener"><x-icon name="external" /><span class="reader__open">Buka di tab baru</span></a>
    </header>
    <iframe class="reader__frame" src="{{ route('reader.file', $book) }}" title="Pembaca PDF: {{ $book->title }}"></iframe>
    <p class="reader__fallback">Peramban Anda tidak menampilkan PDF di halaman ini? <a href="{{ route('reader.file', $book) }}" target="_blank" rel="noopener">Buka PDF di tab baru</a>.</p>
</div>
</x-layouts.reader>
