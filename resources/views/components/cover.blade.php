@props(['book', 'decorative' => true])
@if ($book->cover_url)
    <span class="cover cover--img"><img src="{{ $book->cover_url }}" alt="{{ $decorative ? '' : 'Sampul '.$book->title }}" width="200" height="300" loading="lazy" decoding="async"></span>
@else
    <span class="cover cover--gen cover--c{{ $book->cover_tone }}" @if ($decorative) aria-hidden="true" @else role="img" aria-label="Sampul {{ $book->title }}" @endif>
        <span class="cover__title">{{ $book->title }}</span>
        <span class="cover__author">{{ $book->author }}</span>
    </span>
@endif
