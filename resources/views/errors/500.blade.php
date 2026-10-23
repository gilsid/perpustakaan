<x-layouts.app title="Terjadi masalah di server">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">500</p>
        <h1>Terjadi masalah di server</h1>
        <p>{{ $exception->getMessage() ?: 'Kami tidak bisa menyelesaikan permintaan ini. Coba lagi sebentar lagi.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
