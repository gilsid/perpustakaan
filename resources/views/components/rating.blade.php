@props(['rating' => 0, 'count' => null, 'showCount' => true, 'showNum' => true])
@php
    $rating = (float) $rating;
    $rounded = round($rating * 2) / 2;
@endphp
@if ($count === 0 || ($count === null && $rating <= 0))
    <span class="rating rating--none">Belum ada ulasan</span>
@else
    <span class="rating">
        <span class="stars" role="img" aria-label="Rating {{ number_format($rating, 1, ',', '.') }} dari 5">
            @for ($i = 1; $i <= 5; $i++)
                @php($state = $rounded >= $i ? 'on' : ($rounded >= $i - 0.5 ? 'half' : 'off'))
                <svg class="i star star--{{ $state }}" aria-hidden="true"><use href="#i-star{{ $state === 'half' ? '-half' : '' }}"/></svg>
            @endfor
        </span>
        @if ($showNum)<span class="rating__num">{{ number_format($rating, 1, ',', '.') }}</span>@endif
        @if ($count !== null && $showCount)<span class="rating__count">({{ number_format($count, 0, ',', '.') }})</span>@endif
    </span>
@endif
