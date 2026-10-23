<x-layouts.app title="Halaman tidak ditemukan">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">404</p>
        <h1>Halaman tidak ditemukan</h1>
        <p>{{ $exception->getMessage() ?: 'Alamat yang Anda tuju tidak ada atau sudah dipindahkan.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
