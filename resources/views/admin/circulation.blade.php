<x-layouts.admin title="Sirkulasi" nav="circulation">
<header class="admin-head"><h1>Sirkulasi fisik</h1></header>

<div class="admin-grid admin-grid--cats">
    <section class="card" aria-labelledby="checkout">
        <h2 id="checkout">Check-out</h2>
        <p class="muted small">Pindai kartu anggota dan barcode eksemplar di meja sirkulasi.</p>
        @if ($errors->any())
            <div class="alert alert--error" role="alert"><x-icon name="alert" /><p>{{ $errors->first() }}</p></div>
        @endif
        <form method="post" action="{{ route('admin.circulation.store') }}">
            @csrf
            <div class="field">
                <label for="member">Nomor kartu anggota</label>
                <input id="member" name="member" value="{{ old('member') }}" placeholder="MP-000001" required maxlength="100" autofocus>
            </div>
            <div class="field">
                <label for="barcode">Barcode eksemplar</label>
                <input id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="FIK-0001-01" required maxlength="40">
            </div>
            <button class="btn btn--primary" type="submit"><x-icon name="check" />Pinjamkan</button>
        </form>
    </section>

    <section class="card" aria-labelledby="checkin">
        <h2 id="checkin">Check-in</h2>
        <p class="muted small">Pindai barcode eksemplar yang dikembalikan, lalu pilih kondisinya.</p>
        <form method="post" action="{{ route('admin.circulation.checkin') }}">
            @csrf
            <div class="field">
                <label for="return-barcode">Barcode eksemplar</label>
                <input id="return-barcode" name="barcode" required maxlength="40" placeholder="BK-0001-01">
            </div>
            <div class="field">
                <label for="return-condition">Kondisi</label>
                <select id="return-condition" name="condition" required>
                    @foreach (\App\Enums\BookCopyCondition::cases() as $condition)
                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn--primary" type="submit"><x-icon name="check" />Terima kembali</button>
        </form>
    </section>

    <section class="card" aria-labelledby="terbaru">
        <h2 id="terbaru">Pinjaman fisik terbaru</h2>
        @if ($recent->isEmpty())
            <p class="muted">Belum ada pinjaman fisik.</p>
        @else
            <ul class="feed">
                @foreach ($recent as $l)
                    <li>
                        <strong>{{ $l->book->title }}</strong>
                        <span>{{ $l->copy?->code ?? '–' }} · {{ $l->user->name }} · kembali {{ $l->due_at->translatedFormat('j M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card" aria-labelledby="hold-siap">
        <h2 id="hold-siap">Hold siap diambil</h2>
        @if ($readyHolds->isEmpty())
            <p class="muted">Tidak ada hold yang siap diambil.</p>
        @else
            <ul class="feed">
                @foreach ($readyHolds as $hold)
                    <li>
                        <strong>{{ $hold->book->title }}</strong>
                        <span>{{ $hold->copy?->code ?? '–' }} · {{ $hold->user->name }} · ambil sebelum {{ $hold->pickup_expires_at?->translatedFormat('j M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
</x-layouts.admin>
