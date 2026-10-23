<x-layouts.app title="Akses ditolak">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">403</p>
        <h1>Akses ditolak</h1>
        <p>{{ $exception->getMessage() ?: 'Anda tidak punya izin untuk membuka halaman ini.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
