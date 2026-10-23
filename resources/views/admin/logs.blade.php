<x-layouts.admin title="Log aktivitas" nav="logs">
<header class="admin-head"><h1>Log aktivitas <span class="count">{{ number_format($logs->total(), 0, ',', '.') }}</span></h1></header>
<form class="filter-row" method="get" action="{{ route('admin.logs.index') }}" role="search">
    <label class="sr-only" for="gq">Cari log</label>
    <input id="gq" type="search" name="q" value="{{ $q }}" placeholder="Cari aktivitas atau nama admin">
    <button class="btn btn--secondary" type="submit">Cari</button>
</form>
@if ($logs->isEmpty())
    <div class="empty"><h2>Belum ada aktivitas</h2><p>Perubahan yang dilakukan admin akan tercatat di sini.</p></div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Waktu</th><th scope="col">Pelaku</th><th scope="col">Aktivitas</th><th scope="col">IP</th></tr></thead>
            <tbody>
            @foreach ($logs as $g)
                <tr>
                    <td class="nowrap">{{ $g->created_at->translatedFormat('j M Y, H.i') }}</td>
                    <td>{{ $g->user->name ?? 'Sistem' }}</td>
                    <td>{{ $g->message }}<br><span class="muted small mono">{{ $g->action }}</span></td>
                    <td class="mono small">{{ $g->ip ?? '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
@endif
</x-layouts.admin>
