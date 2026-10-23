@php
    $tabs = ['' => 'Semua', 'aktif' => 'Aktif', 'berakhir' => 'Berakhir', 'selesai' => 'Dikembalikan'];
    $current = $status?->value ?? '';
@endphp
<x-layouts.admin title="Peminjaman" nav="loans">
<header class="admin-head"><h1>Peminjaman <span class="count">{{ number_format($loans->total(), 0, ',', '.') }}</span></h1></header>
<div class="filter-row filter-row--split">
    <nav class="pills" aria-label="Status pinjaman">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('admin.loans.index', ['status' => $k, 'q' => $q]) }}" @if ($current === $k) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="get" action="{{ route('admin.loans.index') }}" role="search">
        <input type="hidden" name="status" value="{{ $current }}">
        <label class="sr-only" for="lq">Cari peminjaman</label>
        <input id="lq" type="search" name="q" value="{{ $q }}" placeholder="Cari anggota atau judul">
    </form>
</div>
@if ($loans->isEmpty())
    <div class="empty"><h2>Tidak ada data</h2><p>Belum ada peminjaman yang cocok dengan filter ini.</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Anggota</th><th scope="col">Buku</th><th scope="col">Dipinjam</th><th scope="col">Batas</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @foreach ($loans as $l)
                <tr>
                    <td>{{ $l->user->name }}<br><span class="muted small">{{ $l->user->email }}</span></td>
                    <td><a href="{{ route('books.show', $l->book) }}">{{ $l->book->title }}</a></td>
                    <td>{{ $l->borrowed_at->translatedFormat('j M Y, H.i') }}</td>
                    <td>{{ $l->due_at->translatedFormat('j M Y') }}</td>
                    <td><span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span></td>
                    <td class="actions">
                        @if ($l->status === \App\Enums\LoanStatus::Active)
                            <form method="post" action="{{ route('admin.loans.update', $l) }}" data-confirm="Akhiri pinjaman “{{ $l->book->title }}” milik {{ $l->user->name }}? Aksesnya langsung ditutup." data-confirm-label="Akhiri pinjaman">
                                @csrf @method('PATCH')<button class="btn btn--secondary btn--sm" type="submit">Akhiri</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $loans->links() }}
@endif
</x-layouts.admin>
