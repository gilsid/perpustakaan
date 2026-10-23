@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Halaman hasil">
        @if (! $paginator->onFirstPage())
            <a class="pagination__step" rel="prev" href="{{ $paginator->previousPageUrl() }}"><x-icon name="chevron-left" />Sebelumnya</a>
        @endif
        <ol class="pagination__pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="pagination__gap" aria-hidden="true">…</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li><a href="{{ $url }}" @if ($page === $paginator->currentPage()) aria-current="page" @endif aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
                    @endforeach
                @endif
            @endforeach
        </ol>
        @if ($paginator->hasMorePages())
            <a class="pagination__step" rel="next" href="{{ $paginator->nextPageUrl() }}">Berikutnya<x-icon name="chevron-right" /></a>
        @endif
    </nav>
@endif
