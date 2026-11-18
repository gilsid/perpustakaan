@php
    $tabs = ['' => 'Semua', 'aktif' => 'Aktif', 'berakhir' => 'Berakhir', 'selesai' => 'Dikembalikan'];
    $current = $status?->value ?? '';
@endphp
<x-layouts.admin title="Peminjaman" nav="loans">
<header class="admin-head"><h1>Peminjaman <span class="count">{{ number_format($loans->total(), 0, ',', '.') }}</span></h1></header>
@if ($errors->any())
    <div class="flashes">
        <div class="alert alert--error" role="alert"><x-icon name="alert" /><p>{{ $errors->first() }}</p></div>
    </div>
@endif
<div class="filter-row filter-row--split">
    <nav class="pills" aria-label="Status pinjaman">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('admin.loans.index', ['status' => $k, 'q' => $q]) }}" @if ($current === $k && ! $fineOnly) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.loans.index', ['denda' => 1, 'q' => $q]) }}" @if ($fineOnly) aria-current="page" @endif>Denda belum lunas</a>
    </nav>
    <form method="get" action="{{ route('admin.loans.index') }}" role="search">
        <input type="hidden" name="status" value="{{ $current }}">
        @if ($fineOnly)<input type="hidden" name="denda" value="1">@endif
        <label class="sr-only" for="lq">Cari peminjaman</label>
        <input id="lq" type="search" name="q" value="{{ $q }}" placeholder="Cari anggota atau judul">
    </form>
</div>
@if ($loans->isEmpty())
    <div class="empty"><h2>Tidak ada data</h2><p>Belum ada peminjaman yang cocok dengan filter ini.</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Anggota</th><th scope="col">Buku</th><th scope="col">Dipinjam</th><th scope="col">Batas</th><th scope="col">Status</th><th scope="col">Denda</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @foreach ($loans as $l)
                <tr>
                    <td>{{ $l->user->name }}<br><span class="muted small">{{ $l->user->email }}</span></td>
                    <td><a href="{{ route('books.show', $l->book) }}">{{ $l->book->title }}</a></td>
                    <td>{{ $l->borrowed_at->translatedFormat('j M Y, H.i') }}</td>
                    <td>{{ $l->due_at->translatedFormat('j M Y') }}</td>
                    <td><span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span></td>
                    <td>
                        @if ($l->fine_amount > 0)
                            <span class="badge {{ $l->fine_status->badgeClass() }}">{{ $l->fine_status->label() }}</span><br>
                            <span class="small">{{ \App\Support\Rupiah::format($l->fine_amount) }} · {{ $l->late_days }} hari</span>
                            @if ($l->fine_status === \App\Enums\FineStatus::Waived)
                                <br><span class="muted small">oleh {{ $l->fineWaivedBy?->name ?? 'admin' }}</span>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($l->returned_at === null)
                            <form method="post" action="{{ route('admin.loans.update', $l) }}" data-confirm="Akhiri pinjaman “{{ $l->book->title }}” milik {{ $l->user->name }}? Aksesnya langsung ditutup dan denda dihitung bila telat." data-confirm-label="Akhiri pinjaman">
                                @csrf @method('PATCH')<button class="btn btn--secondary btn--sm" type="submit">Akhiri</button>
                            </form>
                        @endif
                        @if ($l->fine_status === \App\Enums\FineStatus::Unpaid)
                            <form method="post" action="{{ route('admin.loans.fine.pay', $l) }}" data-confirm="Tandai denda {{ \App\Support\Rupiah::format($l->fine_amount) }} milik {{ $l->user->name }} lunas? Pastikan uang sudah diterima." data-confirm-label="Tandai lunas">
                                @csrf @method('PATCH')<button class="btn btn--secondary btn--sm" type="submit">Lunas</button>
                            </form>
                            <form method="post" action="{{ route('admin.loans.fine.waive', $l) }}">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="reason-{{ $l->id }}">Alasan membebaskan denda</label>
                                <input id="reason-{{ $l->id }}" type="text" name="reason" required minlength="5" maxlength="255" placeholder="Alasan bebas denda">
                                <button class="btn btn--ghost btn--sm" type="submit">Bebaskan</button>
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
