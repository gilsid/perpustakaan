<x-layouts.app title="Formulir kedaluwarsa">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">419</p>
        <h1>Formulir kedaluwarsa</h1>
        <p>{{ $exception->getMessage() ?: 'Sesi formulir sudah habis. Muat ulang halaman lalu kirim lagi.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
