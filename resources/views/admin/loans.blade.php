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
        <a href="{{ route('admin.loans.index', ['denda' => 1, 'q' => $q]) }}" title="Denda belum lunas" @if ($fineOnly) aria-current="page" @endif>Denda</a>
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
        <table class="table table--loans">
            <thead><tr>
                <th scope="col">Anggota</th>
                <th scope="col">Buku</th>
                <th scope="col">Dipinjam</th>
                <th scope="col">Batas</th>
                <th scope="col">Status</th>
                <th scope="col">Denda</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
            </tr></thead>
            <tbody>
            @foreach ($loans as $l)
                <tr>
                    <td><span title="{{ $l->user->email }}">{{ $l->user->name }}</span></td>
                    <td>
                        <a href="{{ route('books.show', $l->book) }}">{{ $l->book->title }}</a>
                        @if ($l->isPhysical()) <span class="small muted">{{ $l->copy?->code ?? 'Fisik' }}</span>@endif
                    </td>
                    <td class="nowrap" title="{{ $l->borrowed_at->translatedFormat('j M Y, H.i') }}">{{ $l->borrowed_at->translatedFormat('j M Y') }}</td>
                    <td class="nowrap" title="{{ $l->due_at->translatedFormat('j M Y, H.i') }}">{{ $l->due_at->translatedFormat('j M Y') }}</td>
                    <td><span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span></td>
                    <td class="fine-cell">
                        @if ($l->fine_amount > 0)
                            <span class="badge {{ $l->fine_status->badgeClass() }}">{{ $l->fine_status->label() }}</span>
                            <span class="fine-cell__amount">{{ \App\Support\Rupiah::format($l->fine_amount) }} · {{ $l->late_days }} hari</span>
                            @if ($l->fine_status === \App\Enums\FineStatus::Waived)
                                <span class="fine-cell__note">oleh {{ $l->fineWaivedBy?->name ?? 'admin' }}</span>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($l->returned_at === null)
                            <form method="post" action="{{ route('admin.loans.update', $l) }}" data-confirm="Akhiri pinjaman “{{ $l->book->title }}” milik {{ $l->user->name }}? Aksesnya langsung ditutup dan denda dihitung bila telat." data-confirm-label="Akhiri pinjaman">
                                @csrf @method('PATCH')
                                <button class="act-btn" type="submit" title="Akhiri pinjaman"><x-icon name="x" />Akhiri</button>
                            </form>
                        @endif
                        @if ($l->fine_status->canBeSettled())
                            <form method="post" action="{{ route('admin.loans.fine.pay', $l) }}" data-confirm="Tandai denda {{ \App\Support\Rupiah::format($l->fine_amount) }} milik {{ $l->user->name }} lunas? Pastikan uang sudah diterima." data-confirm-label="Tandai lunas">
                                @csrf @method('PATCH')
                                <button class="act-btn act-btn--ok" type="submit" title="Tandai lunas"><x-icon name="check" />Lunas</button>
                            </form>
                            <button type="button" class="act-btn" data-waive-action="{{ route('admin.loans.fine.waive', $l) }}" data-waive-book="{{ $l->book->title }}" data-waive-user="{{ $l->user->name }}" data-waive-amount="{{ \App\Support\Rupiah::format($l->fine_amount) }}">Bebaskan</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $loans->links() }}
@endif

<dialog id="waive-dialog" class="dialog" aria-labelledby="waive-title">
    <form method="post">
        @csrf @method('PATCH')
        <h2 id="waive-title" class="dialog__title">Bebaskan denda</h2>
        <p id="waive-message" class="dialog__message"></p>
        <div class="field">
            <label for="waive-reason">Alasan pembebasan</label>
            <input id="waive-reason" type="text" name="reason" required minlength="5" maxlength="255" placeholder="Contoh: kesalahan pencatatan tenggat">
        </div>
        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-close>Batal</button>
            <button type="submit" class="btn btn--primary">Bebaskan denda</button>
        </div>
    </form>
</dialog>
</x-layouts.admin>
