@props(['book'])
<article class="book-card">
    <div class="book-card__cover"><x-cover :book="$book" /></div>
    <h3 class="book-card__title"><a class="book-card__link" href="{{ route('books.show', $book) }}">{{ $book->title }}</a></h3>
    <p class="book-card__author">{{ $book->author }}</p>
    <p class="book-card__meta">
        <x-rating :rating="$book->rating" :count="$book->review_count" :show-count="false" />
        @if ($book->has_pdf)
            <span class="tag tag--digital"><x-icon name="book" />Bisa dibaca</span>
        @else
            <span class="tag">Katalog</span>
        @endif
    </p>
</article>
