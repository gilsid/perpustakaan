<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Audit;
use App\Support\Query;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly Audit $audit) {}

    public function index(Request $request): View
    {
        $q = Query::string($request, 'q');

        $reviews = Review::query()
            ->with(['book', 'user'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->whereHas('book', fn ($b) => $b->where('title', 'ilike', $like))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $like))
                    ->orWhere('comment', 'ilike', $like));
            })
            ->latest()
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.reviews', ['nav' => 'reviews', 'reviews' => $reviews, 'q' => $q]);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();
        $this->audit->reviewDeleted($review, request()->user());

        return back()->with('success', 'Ulasan dihapus.');
    }
}
