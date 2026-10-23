<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Services\BookMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReaderController extends Controller
{
    public function __construct(private readonly BookMedia $media) {}

    /** Halaman pembaca (membungkus PDF dalam iframe). */
    public function show(Request $request, Book $book): View|RedirectResponse
    {
        abort_unless($book->has_pdf, 404, 'Buku ini belum punya berkas digital.');

        if ($request->user()->cannot('read', $book)) {
            return redirect()->route('books.show', $book)->with('warning', 'Pinjam buku ini dulu untuk membacanya.');
        }

        $loan = $request->user()->isAdmin()
            ? null
            : Loan::query()->where('user_id', $request->user()->id)->where('book_id', $book->id)->active()->first();

        return view('books.reader', ['book' => $book, 'loan' => $loan]);
    }

    /**
     * Menyajikan PDF dari disk privat. BinaryFileResponse mendukung HTTP Range,
     * jadi penampil PDF bisa melompat antarhalaman tanpa mengunduh seluruh berkas.
     */
    public function file(Book $book): BinaryFileResponse
    {
        abort_unless($book->has_pdf, 404, 'Buku ini belum punya berkas digital.');
        abort_unless(auth()->user()->can('read', $book), 403, 'Anda belum meminjam buku ini.');
        abort_unless($this->media->pdfExists($book->pdf_path), 404, 'Berkas PDF tidak ditemukan di server.');

        return response()->file($this->media->pdfAbsolutePath($book->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($book->title).'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
    }
}
