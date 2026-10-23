@php
    $max = max(1, $perDay->max('n'));
    $sum = $perDay->sum('n');
    $W = 560; $H = 140; $n = $perDay->count(); $slot = $W / max(1, $n); $bw = $slot * 0.62;
@endphp
<x-layouts.admin title="Dasbor" nav="dashboard">
<header class="admin-head"><h1>Dasbor</h1>
    <a class="btn btn--primary" href="{{ route('admin.books.create') }}"><x-icon name="plus" />Tambah buku</a>
</header>

<ul class="stats">
    <li><span class="stats__label">Judul buku</span><span class="stats__num">{{ number_format($titles, 0, ',', '.') }}</span><span class="stats__sub">{{ number_format($digital, 0, ',', '.') }} punya berkas digital</span></li>
    <li><span class="stats__label">Anggota</span><span class="stats__num">{{ number_format($members, 0, ',', '.') }}</span><span class="stats__sub"><a href="{{ route('admin.users.index') }}">Kelola pengguna</a></span></li>
    <li><span class="stats__label">Pinjaman aktif</span><span class="stats__num">{{ number_format($activeLoans, 0, ',', '.') }}</span><span class="stats__sub">{{ number_format($expiring, 0, ',', '.') }} berakhir dalam 3 hari</span></li>
    <li><span class="stats__label">Ulasan</span><span class="stats__num">{{ number_format($reviewCount, 0, ',', '.') }}</span><span class="stats__sub"><a href="{{ route('admin.reviews.index') }}">Moderasi ulasan</a></span></li>
</ul>

<div class="admin-grid">
    <section class="card" aria-labelledby="grafik">
        <h2 id="grafik">Peminjaman 14 hari terakhir</h2>
        <p class="muted small">{{ number_format($sum, 0, ',', '.') }} peminjaman baru</p>
        <svg class="chart" viewBox="0 0 {{ $W }} {{ $H + 24 }}" role="img" aria-label="Grafik batang peminjaman per hari, total {{ $sum }} dalam 14 hari">
            @foreach ($perDay as $i => $d)
                @php
                    $h = (int) round(($d->n / $max) * ($H - 18));
                    $x = $i * $slot + ($slot - $bw) / 2;
                    $date = \Illuminate\Support\Carbon::parse($d->day);
                @endphp
                <rect @class(['chart__bar', 'is-today' => $i === $n - 1]) x="{{ round($x, 1) }}" y="{{ $H - $h }}" width="{{ round($bw, 1) }}" height="{{ max($h, 2) }}" rx="3"><title>{{ $date->translatedFormat('j M Y') }}: {{ $d->n }} peminjaman</title></rect>
                @if ($d->n > 0)<text class="chart__val" x="{{ round($x + $bw / 2, 1) }}" y="{{ $H - $h - 5 }}" text-anchor="middle">{{ $d->n }}</text>@endif
                <text class="chart__lbl" x="{{ round($x + $bw / 2, 1) }}" y="{{ $H + 17 }}" text-anchor="middle">{{ $date->format('j') }}</text>
            @endforeach
            <line class="chart__axis" x1="0" x2="{{ $W }}" y1="{{ $H }}" y2="{{ $H }}"/>
        </svg>
    </section>

    <section class="card" aria-labelledby="terlaris">
        <h2 id="terlaris">Paling sering dipinjam</h2>
        @if ($topBooks->isEmpty())
            <p class="muted">Belum ada data peminjaman.</p>
        @else
            <ol class="rank">
                @foreach ($topBooks as $b)
                    <li><a href="{{ route('admin.books.edit', $b) }}">{{ $b->title }}</a><span>{{ number_format($b->loans_count, 0, ',', '.') }}×</span></li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

<div class="admin-grid admin-grid--even">
    <section class="card" aria-labelledby="aktif">
        <header class="card__head"><h2 id="aktif">Pinjaman aktif terbaru</h2><a href="{{ route('admin.loans.index') }}">Semua</a></header>
        @if ($latestLoans->isEmpty())
            <p class="muted">Tidak ada pinjaman aktif.</p>
        @else
            <ul class="feed">
                @foreach ($latestLoans as $l)
                    <li><strong>{{ $l->book->title }}</strong><span>{{ $l->user->name }} · berakhir {{ $l->due_at->translatedFormat('j M Y') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
    <section class="card" aria-labelledby="aktivitas">
        <header class="card__head"><h2 id="aktivitas">Aktivitas terakhir</h2><a href="{{ route('admin.logs.index') }}">Semua</a></header>
        @if ($recent->isEmpty())
            <p class="muted">Belum ada aktivitas.</p>
        @else
            <ul class="feed">
                @foreach ($recent as $g)
                    <li><strong>{{ $g->message }}</strong><span>{{ $g->user->name ?? 'Sistem' }} · {{ $g->created_at->translatedFormat('j M Y, H.i') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
</x-layouts.admin>
