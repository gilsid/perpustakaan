<x-layouts.app title="Terlalu banyak permintaan">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">429</p>
        <h1>Terlalu banyak permintaan</h1>
        <p>{{ $exception->getMessage() ?: 'Anda terlalu sering mengirim permintaan. Tunggu sebentar lalu coba lagi.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
