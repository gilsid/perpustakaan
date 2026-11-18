<?php

namespace App\Http\Controllers;

use App\Exceptions\LoanException;
use App\Models\Book;
use App\Services\LoanService;
use App\Support\Rupiah;
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

    /** Mengembalikan buku. Denda muncul bila melewati tenggat. */
    public function destroy(Request $request, Book $book): RedirectResponse
    {
        try {
            $loan = $this->loans->giveBack($request->user(), $book);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Buku dikembalikan.';

        // Denda bisa nol walau telat (mis. tarif 0), jadi pesan mengikuti hari telat,
        // bukan nominal — skorsing tetap perlu diberitahukan.
        if ($loan->late_days > 0) {
            if ($loan->fine_amount > 0) {
                $message .= ' Denda '.Rupiah::format($loan->fine_amount)." ({$loan->late_days} hari telat).";
            }

            $until = $request->user()->fresh()->suspend_until;
            if ($until !== null && $until->isFuture()) {
                $message .= ' Akun diskors sampai '.$until->translatedFormat('j M Y').'.';
            }
        }

        return back()->with('success', $message);
    }
}
