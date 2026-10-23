<x-layouts.app title="Sedang dalam pemeliharaan">
<div class="container page">
    <div class="empty empty--error">
        <p class="empty__code">503</p>
        <h1>Sedang dalam pemeliharaan</h1>
        <p>{{ $exception->getMessage() ?: 'Situs akan kembali sebentar lagi.' }}</p>
        <p class="empty__actions">
            <a class="btn btn--primary" href="{{ route('home') }}">Ke beranda</a>
            <a class="btn btn--ghost" href="{{ route('catalog.index') }}">Buka katalog</a>
        </p>
    </div>
</div>
</x-layouts.app>
