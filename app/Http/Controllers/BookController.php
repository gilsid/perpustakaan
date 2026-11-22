<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Hold;
use App\Models\Review;
use App\Services\BorrowEligibility;
use App\Services\LoanAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function __construct(
        private readonly BorrowEligibility $eligibility,
        private readonly LoanAccess $access,
    ) {}

    public function show(Request $request, Book $book): View
    {
        $book = Book::query()->with('category')->withStats()->findOrFail($book->id);
        $user = $request->user();
        $isMember = $user?->isMember() ?? false;

        $activeLoan = $isMember ? $this->access->activeFor($user, $book) : null;
        $myReview = $isMember ? Review::query()->where('user_id', $user->id)->where('book_id', $book->id)->first() : null;
        $canReview = $isMember && (! config('library.review_requires_loan')
            || $this->access->hasBorrowed($user, $book));
        $standing = $isMember && $activeLoan === null ? $this->eligibility->forBook($user, $book) : null;
        $pendingHold = $isMember ? Hold::query()->where('user_id', $user->id)->where('book_id', $book->id)->pending()->first() : null;
        $canHold = $isMember && $activeLoan === null && $pendingHold === null
            && $book->copies()->exists() && $book->available_copies_count === 0;

        $distribution = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($book->reviews()->selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating') as $rating => $n) {
            $distribution[(int) $rating] = (int) $n;
        }

        return view('books.show', [
            'book' => $book,
            'reviews' => $book->reviews()->with('user')->latest()->get(),
            'distribution' => $distribution,
            'isFavorite' => $isMember && $user->favorites()->whereKey($book->id)->exists(),
            'activeLoan' => $activeLoan,
            'standing' => $standing,
            'pendingHold' => $pendingHold,
            'canHold' => $canHold,
            'myReview' => $myReview,
            'canReview' => $canReview,
            'sameAuthor' => Book::query()->with('category')->withStats()
                ->whereRaw('lower(author) = lower(?)', [$book->author])->whereKeyNot($book->id)
                ->orderByRaw('published_year DESC NULLS LAST')->limit(6)->get(),
            'related' => $book->category_id
                ? Book::query()->with('category')->withStats()->where('category_id', $book->category_id)->whereKeyNot($book->id)->latest()->limit(6)->get()
                : collect(),
        ]);
    }
}
