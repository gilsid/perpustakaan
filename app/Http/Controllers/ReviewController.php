<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Book;
use App\Models\Review;
use App\Services\Audit;
use App\Services\LoanAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(
        private readonly LoanAccess $access,
        private readonly Audit $audit,
    ) {}

    /** Menulis atau mengubah ulasan (satu per anggota per buku). */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $user = $request->user();
        $target = route('books.show', $book).'#ulasan';

        if (config('library.review_requires_loan') && ! $this->access->hasBorrowed($user, $book)) {
            return redirect($target)->with('error', 'Pinjam buku ini dulu untuk bisa menulis ulasan.');
        }

        $review = Review::query()->updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            ['rating' => $request->integer('rating'), 'comment' => (string) $request->input('comment')],
        );

        return redirect($target)->with('success', $review->wasRecentlyCreated ? 'Terima kasih, ulasan Anda tersimpan.' : 'Ulasan Anda diperbarui.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $isOwner = $review->user_id === auth()->id();
        $review->delete();
        if (! $isOwner) {
            $this->audit->reviewDeleted($review, auth()->user());
        }

        return back()->with('success', 'Ulasan dihapus.');
    }
}
