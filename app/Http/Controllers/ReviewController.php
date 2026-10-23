<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Loan;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    /** Menulis atau mengubah ulasan (satu per anggota per buku). */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $user = $request->user();
        $target = route('books.show', $book).'#ulasan';

        if (config('library.review_requires_loan') && ! Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->exists()) {
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
            ActivityLog::record('delete_review', 'review', $review->id, "Menghapus ulasan #{$review->id} pada buku #{$review->book_id}");
        }

        return back()->with('success', 'Ulasan dihapus.');
    }
}
