<x-layouts.app title="Pinjaman saya">
<div class="container page">
    <header class="page__head"><h1>Rak saya</h1></header>
    <x-account-tabs :tab="$tab" />

    @if ($standing?->isBlocked())
        <div class="flashes">
            @foreach ($standing->blocks as $block)
                <div class="alert alert--warning" role="status"><x-icon name="alert" /><p>{{ $block->message() }}</p></div>
            @endforeach
        </div>
    @endif

    @if ($holds->isNotEmpty())
        <section class="section section--tight" aria-labelledby="hold">
            <h2 id="hold">Hold saya <span class="count">{{ $holds->count() }}</span></h2>
            <ul class="feed">
                @foreach ($holds as $hold)
                    <li>
                        <strong><a href="{{ route('books.show', $hold->book) }}">{{ $hold->book->title }}</a></strong>
                        <span><span class="badge {{ $hold->status->badgeClass() }}">{{ $hold->status->label() }}</span>
                        @if ($hold->pickup_expires_at) · ambil sebelum {{ $hold->pickup_expires_at->translatedFormat('j M Y, H.i') }}@endif</span>
                        <form method="post" action="{{ route('holds.destroy', $hold) }}">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm" type="submit">Batalkan</button></form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($overdue->isNotEmpty())
        <section class="section section--tight" aria-labelledby="terlambat">
            <h2 id="terlambat">Terlambat dikembalikan <span class="count">{{ $overdue->count() }}</span></h2>
            <ul class="loan-list">
                @foreach ($overdue as $l)
                    <li class="loan">
                        <a class="loan__cover" href="{{ route('books.show', $l->book) }}"><x-cover :book="$l->book" /></a>
                        <div class="loan__body">
                            <h3><a href="{{ route('books.show', $l->book) }}">{{ $l->book->title }}</a> @if ($l->isPhysical()) <span class="badge badge--muted">Fisik</span>@endif</h3>
                            <p class="muted">{{ $l->book->author }}</p>
                            <p class="loan__due is-soon"><x-icon name="alert" />Lewat tenggat {{ $l->due_at->translatedFormat('j M Y') }} · @if ($l->days_late > 0){{ $l->days_late }} hari telat @else masih dalam masa tenggang @endif</p>
                            <p class="hint">@if ($l->days_late > 0)Denda dihitung saat buku dikembalikan.@else Denda mulai {{ $l->grace_ends_at->translatedFormat('j M Y, H.i') }}.@endif</p>
                        </div>
                        <div class="loan__actions">
                            @if ($l->isDigital())
                                <form method="post" action="{{ route('loans.destroy', $l->book) }}" data-confirm="Kembalikan “{{ $l->book->title }}”? Denda keterlambatan akan dicatat." data-confirm-label="Kembalikan">@csrf @method('DELETE')<button class="btn btn--ghost" type="submit">Kembalikan sekarang</button></form>
                            @else
                                <p class="muted small">Kembalikan di meja sirkulasi.</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="section section--tight" aria-labelledby="sedang">
        <h2 id="sedang">Sedang dipinjam <span class="count">{{ $active->count() }} / {{ $standing->maxActive }}</span></h2>
        @if ($active->isEmpty())
            <div class="empty empty--inline">
                <p>Anda belum meminjam buku apa pun.</p>
                <a class="btn btn--primary" href="{{ route('catalog.index', ['digital' => '1']) }}">Cari buku yang bisa dipinjam</a>
            </div>
        @else
            <ul class="loan-list">
                @foreach ($active as $l)
                    <li class="loan">
                        <a class="loan__cover" href="{{ route('books.show', $l->book) }}"><x-cover :book="$l->book" /></a>
                        <div class="loan__body">
                            <h3><a href="{{ route('books.show', $l->book) }}">{{ $l->book->title }}</a> @if ($l->isPhysical()) <span class="badge badge--muted">Fisik</span>@endif</h3>
                            <p class="muted">{{ $l->book->author }}</p>
                            <p @class(['loan__due', 'is-soon' => $l->is_due_soon])><x-icon name="clock" />Berakhir {{ $l->due_at->translatedFormat('j M Y') }} · {{ $l->days_left_label }}</p>
                            <progress class="loan__progress" max="100" value="{{ $l->elapsed_percent }}" aria-label="Masa pinjam terpakai"></progress>
                        </div>
                        <div class="loan__actions">
                            @if ($l->isDigital())
                                <a class="btn btn--primary" href="{{ route('reader.show', $l->book) }}">Baca</a>
                                @if ($l->can_renew)
                                    <form method="post" action="{{ route('loans.update', $l->book) }}">@csrf @method('PATCH')<button class="btn btn--secondary" type="submit"><x-icon name="refresh" />Perpanjang</button></form>
                                @endif
                                <form method="post" action="{{ route('loans.destroy', $l->book) }}" data-confirm="Kembalikan “{{ $l->book->title }}”?" data-confirm-label="Kembalikan">@csrf @method('DELETE')<button class="btn btn--ghost" type="submit">Kembalikan</button></form>
                            @else
                                <p class="muted small">Kembalikan di meja sirkulasi.</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="section section--tight" aria-labelledby="riwayat">
        <h2 id="riwayat">Riwayat</h2>
        @if ($history->isEmpty())
            <p class="muted">Riwayat peminjaman akan muncul di sini.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Judul</th><th scope="col">Dipinjam</th><th scope="col">Selesai</th><th scope="col">Denda</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @foreach ($history as $h)
                        <tr>
                            <td><a href="{{ route('books.show', $h->book) }}">{{ $h->book->title }}</a> @if ($h->isPhysical()) <span class="badge badge--muted">Fisik</span>@endif</td>
                            <td>{{ $h->borrowed_at->translatedFormat('j M Y') }}</td>
                            <td>{{ $h->returned_at->translatedFormat('j M Y') }}</td>
                            <td>
                                @if ($h->fine_amount > 0)
                                    <span class="badge {{ $h->fine_status->badgeClass() }}">{{ $h->fine_status->label() }}</span>
                                    <span class="muted small">{{ \App\Support\Rupiah::format($h->fine_amount) }} · {{ $h->late_days }} hari</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                                @if ($h->replacement_amount > 0)
                                    <br><span class="muted small">Ganti: {{ \App\Support\Rupiah::format($h->replacement_amount) }} ({{ $h->replacementStatusLabel() }})</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $h->status->badgeClass() }}">{{ $h->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
</x-layouts.app>
