<x-layouts.admin title="Ulasan" nav="reviews">
<header class="admin-head"><h1>Ulasan <span class="count">{{ number_format($reviews->total(), 0, ',', '.') }}</span></h1></header>
<form class="filter-row" method="get" action="{{ route('admin.reviews.index') }}" role="search">
    <label class="sr-only" for="rq">Cari ulasan</label>
    <input id="rq" type="search" name="q" value="{{ $q }}" placeholder="Cari judul, nama, atau isi ulasan">
    <button class="btn btn--secondary" type="submit">Cari</button>
</form>
@if ($reviews->isEmpty())
    <div class="empty"><h2>Belum ada ulasan</h2><p>Ulasan dari anggota akan muncul di sini untuk dimoderasi.</p></div>
@else
    <ul class="mod-list">
        @foreach ($reviews as $r)
            <li class="card mod">
                <div class="mod__head">
                    <a href="{{ route('books.show', $r->book) }}#ulasan"><strong>{{ $r->book->title }}</strong></a>
                    <x-rating :rating="$r->rating" :count="null" />
                </div>
                <p class="muted small">{{ $r->user->name }} · {{ $r->created_at->translatedFormat('j M Y, H.i') }}</p>
                @if (trim($r->comment) !== '')<p>{!! nl2br(e($r->comment)) !!}</p>@else<p class="muted">Tanpa komentar.</p>@endif
                <form method="post" action="{{ route('admin.reviews.destroy', $r) }}" data-confirm="Hapus ulasan dari {{ $r->user->name }}?" data-confirm-label="Hapus ulasan">
                    @csrf @method('DELETE')<button class="link-danger" type="submit"><x-icon name="trash" />Hapus ulasan</button>
                </form>
            </li>
        @endforeach
    </ul>
    {{ $reviews->links() }}
@endif
</x-layouts.admin>
