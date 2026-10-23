<x-layouts.app title="Metode tidak diizinkan">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">405</p>
        <h1>Metode tidak diizinkan</h1>
        <p>{{ $exception->getMessage() ?: 'Permintaan ini tidak bisa diproses.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
