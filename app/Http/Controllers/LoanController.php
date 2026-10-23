<?php

namespace App\Http\Controllers;

use App\Exceptions\LoanException;
use App\Models\Book;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    /** Meminjam buku. */
    public function store(Request $request, Book $book): RedirectResponse
    {
        try {
            $this->loans->borrow($request->user(), $book);
        } catch (LoanException $e) {
            return redirect()->route('books.show', $book)->with('error', $e->getMessage());
        }

        $days = config('library.loan.days');

        return redirect()->route('books.show', $book)->with('success', "Buku dipinjam selama $days hari. Selamat membaca!");
    }

    /** Memperpanjang pinjaman aktif. */
    public function update(Request $request, Book $book): RedirectResponse
    {
        try {
            $this->loans->renew($request->user(), $book);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pinjaman diperpanjang '.config('library.loan.days').' hari.');
    }

    /** Mengembalikan buku. */
    public function destroy(Request $request, Book $book): RedirectResponse
    {
        try {
            $this->loans->giveBack($request->user(), $book);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Buku dikembalikan.');
    }
}
