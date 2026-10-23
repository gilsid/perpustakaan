@php
    $user = auth()->user();
    $isMember = $user?->isMember() ?? false;
    $isAdmin = $user?->isAdmin() ?? false;
    $maxLoans = config('library.loan.max_active');
    $days = config('library.loan.days');
    $totalReviews = array_sum($distribution);
@endphp
<x-layouts.app :title="$book->title">
<div class="container page">
    <nav class="breadcrumb" aria-label="Jejak halaman">
        <a href="{{ route('catalog.index') }}">Katalog</a>
        @if ($book->category)<x-icon name="chevron-right" /><a href="{{ route('catalog.index', ['kategori' => $book->category->slug]) }}">{{ $book->category->name }}</a>@endif
    </nav>

    <div class="book">
        <div class="book__cover"><x-cover :book="$book" :decorative="false" /></div>

        <div class="book__main">
            <h1 class="book__title">{{ $book->title }}</h1>
            <p class="book__author">oleh <a href="{{ route('catalog.index', ['q' => $book->author]) }}">{{ $book->author }}</a></p>
            <p class="book__rating">
                <x-rating :rating="$book->rating" :count="$book->review_count" />
                @if ($book->review_count > 0)<a href="#ulasan">Baca ulasan</a>@endif
            </p>

            <section class="loan-box" aria-label="Peminjaman">
                @if ($isAdmin)
                    <p class="loan-box__status"><x-icon name="shield" />Anda masuk sebagai admin.</p>
                    <div class="loan-box__actions">
                        @if ($book->has_pdf)<a class="btn btn--primary" href="{{ route('reader.show', $book) }}"><x-icon name="book" />Buka untuk membaca</a>@endif
                        <a class="btn btn--secondary" href="{{ route('admin.books.edit', $book) }}"><x-icon name="edit" />Ubah buku</a>
                    </div>
                @elseif (! $book->has_pdf)
                    <p class="loan-box__status"><x-icon name="info" />Buku ini tercatat di katalog, tetapi belum tersedia dalam bentuk digital.</p>
                    @if ($isMember)
                        @include('books._favorite', ['book' => $book, 'isFavorite' => $isFavorite, 'class' => 'btn btn--secondary'])
                    @endif
                @elseif (! $user)
                    <p class="loan-box__status"><x-icon name="book" />Bisa dipinjam {{ $days }} hari dan dibaca online.</p>
                    <div class="loan-box__actions">
                        <a class="btn btn--primary" href="{{ route('login', ['next' => '/buku/'.$book->id]) }}">Masuk untuk meminjam</a>
                        <a class="btn btn--ghost" href="{{ route('register') }}">Belum punya akun? Daftar</a>
                    </div>
                @elseif ($activeLoan)
                    <p class="loan-box__status loan-box__status--on"><x-icon name="clock" />Sedang Anda pinjam. Berakhir <strong>{{ $activeLoan->due_at->translatedFormat('j M Y') }}</strong> ({{ $activeLoan->days_left_label }}).</p>
                    <div class="loan-box__actions">
                        <a class="btn btn--primary" href="{{ route('reader.show', $book) }}"><x-icon name="book" />Baca sekarang</a>
                        @if ($activeLoan->can_renew)
                            <form method="post" action="{{ route('loans.update', $book) }}">@csrf @method('PATCH')<button class="btn btn--secondary" type="submit"><x-icon name="refresh" />Perpanjang {{ $days }} hari</button></form>
                        @endif
                        <form method="post" action="{{ route('loans.destroy', $book) }}" data-confirm="Kembalikan buku ini? Anda tidak bisa membacanya lagi sebelum meminjam ulang." data-confirm-label="Kembalikan">@csrf @method('DELETE')<button class="btn btn--ghost" type="submit">Kembalikan</button></form>
                    </div>
                @else
                    <p class="loan-box__status"><x-icon name="book" />Tersedia untuk dipinjam {{ $days }} hari. Anda meminjam {{ $activeCount }} dari {{ $maxLoans }} buku.</p>
                    <div class="loan-box__actions">
                        <form method="post" action="{{ route('loans.store', $book) }}">@csrf
                            <button class="btn btn--primary btn--lg" type="submit" @disabled($activeCount >= $maxLoans)>Pinjam buku</button>
                        </form>
                        @if ($activeCount >= $maxLoans)<p class="hint">Batas pinjaman tercapai. Kembalikan satu buku dulu.</p>@endif
                    </div>
                @endif
                @if ($isMember && $book->has_pdf)
                    @include('books._favorite', ['book' => $book, 'isFavorite' => $isFavorite, 'class' => 'btn btn--ghost', 'wrap' => 'loan-box__fav'])
                @endif
            </section>

            @if (trim($book->description) !== '')
                <section class="prose" aria-labelledby="sinopsis">
                    <h2 id="sinopsis">Sinopsis</h2>
                    {!! nl2br(e($book->description)) !!}
                </section>
            @endif

            <section class="catalog-card" aria-labelledby="rekaman">
                <h2 id="rekaman" class="catalog-card__title">Rekaman katalog</h2>
                <dl class="catalog-card__list">
                    @if ($book->call_number)<div><dt>Nomor panggil</dt><dd class="mono">{{ $book->call_number }}</dd></div>@endif
                    <div><dt>Penulis</dt><dd>{{ $book->author }}</dd></div>
                    @if ($book->category)<div><dt>Kategori</dt><dd><a href="{{ route('catalog.index', ['kategori' => $book->category->slug]) }}">{{ $book->category->name }}</a></dd></div>@endif
                    @if ($book->publisher)<div><dt>Penerbit</dt><dd>{{ $book->publisher }}</dd></div>@endif
                    @if ($book->published_year)<div><dt>Tahun terbit</dt><dd>{{ $book->published_year }}</dd></div>@endif
                    @if ($book->language)<div><dt>Bahasa</dt><dd>{{ $book->language }}</dd></div>@endif
                    @if ($book->pages)<div><dt>Halaman</dt><dd>{{ number_format($book->pages, 0, ',', '.') }}</dd></div>@endif
                    @if ($book->isbn)<div><dt>ISBN</dt><dd class="mono">{{ $book->isbn }}</dd></div>@endif
                    <div><dt>Format</dt><dd>{{ $book->has_pdf ? 'Buku digital (PDF)' : 'Hanya katalog' }}</dd></div>
                    <div><dt>Dipinjam</dt><dd>{{ number_format($book->loan_count, 0, ',', '.') }} kali</dd></div>
                </dl>
            </section>
        </div>
    </div>

    <section class="reviews" id="ulasan" aria-labelledby="ulasan-judul">
        <h2 id="ulasan-judul">Ulasan pembaca</h2>
        <div class="reviews__grid">
            <div class="reviews__summary">
                @if ($totalReviews > 0)
                    <p class="reviews__score">{{ number_format($book->rating, 1, ',', '.') }}<span> dari 5</span></p>
                    <x-rating :rating="$book->rating" :count="$totalReviews" :show-num="false" />
                    <ul class="bars" aria-label="Sebaran rating">
                        @foreach ($distribution as $star => $n)
                            <li><span>{{ $star }} bintang</span><progress max="{{ $totalReviews }}" value="{{ $n }}"></progress><span>{{ $n }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">Belum ada ulasan untuk buku ini.</p>
                @endif

                @if ($isMember)
                    @if ($canReview)
                        <form class="review-form" method="post" action="{{ route('reviews.store', $book) }}">
                            @csrf @method('PUT')
                            <h3>{{ $myReview ? 'Ubah ulasan Anda' : 'Tulis ulasan' }}</h3>
                            @php($currentRating = (int) old('rating', $myReview->rating ?? 0))
                            <fieldset class="rate">
                                <legend>Rating Anda</legend>
                                <div class="rate__row">
                                    @for ($i = 5; $i >= 1; $i--)
                                        <input type="radio" id="rate-{{ $i }}" name="rating" value="{{ $i }}" @checked($currentRating === $i) @required($i === 1)>
                                        <label for="rate-{{ $i }}"><x-icon name="star" /><span class="sr-only">{{ $i }} bintang</span></label>
                                    @endfor
                                </div>
                                @error('rating')<p class="error"><x-icon name="alert" /><span>{{ $message }}</span></p>@enderror
                            </fieldset>
                            <x-field name="comment" label="Ulasan (opsional)" type="textarea" :rows="4" :value="$myReview->comment ?? ''" maxlength="1000" />
                            <button class="btn btn--primary" type="submit">{{ $myReview ? 'Simpan perubahan' : 'Kirim ulasan' }}</button>
                        </form>
                    @else
                        <p class="hint"><x-icon name="info" />Pinjam buku ini untuk bisa menulis ulasan.</p>
                    @endif
                @elseif (! $user)
                    <p class="hint"><a href="{{ route('login', ['next' => '/buku/'.$book->id]) }}">Masuk</a> untuk menulis ulasan.</p>
                @endif
            </div>

            <ul class="review-list">
                @foreach ($reviews as $r)
                    <li class="review">
                        <div class="review__head">
                            <strong>{{ $r->user->name }}</strong>
                            <x-rating :rating="$r->rating" :count="null" />
                            <time datetime="{{ $r->created_at->toIso8601String() }}">{{ $r->created_at->translatedFormat('j M Y') }}</time>
                        </div>
                        @if (trim($r->comment) !== '')<p>{!! nl2br(e($r->comment)) !!}</p>@endif
                        @if ($isAdmin || ($user && $user->id === $r->user_id))
                            <form method="post" action="{{ route('reviews.destroy', $r) }}" data-confirm="Hapus ulasan ini?" data-confirm-label="Hapus">@csrf @method('DELETE')
                                <button class="link-danger" type="submit"><x-icon name="trash" />Hapus ulasan</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @foreach ([['Buku lain dari '.$book->author, $sameAuthor], ['Lainnya di '.($book->category->name ?? 'kategori ini'), $related]] as [$heading, $list])
        @if ($list->isNotEmpty())
            <section class="section section--tight">
                <header class="section__head"><h2>{{ $heading }}</h2></header>
                <div class="book-grid">@foreach ($list as $b)<x-book-card :book="$b" />@endforeach</div>
            </section>
        @endif
    @endforeach
</div>
</x-layouts.app>
