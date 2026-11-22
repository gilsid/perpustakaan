<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookCopyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookCopyRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Services\Audit;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookCopyController extends Controller
{
    public function __construct(private readonly Audit $audit) {}

    public function index(Request $request): View
    {
        $search = SearchTerm::fromRequest($request);

        $copies = BookCopy::query()
            ->with('book')
            ->when(! $search->isBlank(), fn ($query) => $query
                ->where('code', 'ilike', $search->like())
                ->orWhere('location', 'ilike', $search->like())
                ->orWhereHas('book', fn ($b) => $b->where('title', 'ilike', $search->like())))
            ->orderBy('code')
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.copies.index', [
            'nav' => 'copies',
            'copies' => $copies,
            'q' => $search->value,
        ]);
    }

    public function create(): View
    {
        return view('admin.copies.form', [
            'nav' => 'copies',
            'copy' => new BookCopy,
            'books' => Book::orderBy('title')->get(['id', 'title']),
            'statuses' => BookCopyStatus::cases(),
        ]);
    }

    public function store(BookCopyRequest $request): RedirectResponse
    {
        $copy = BookCopy::create($request->validated());
        $this->audit->copyCreated($copy, $request->user());

        return redirect()->route('admin.copies.index')->with('success', "Eksemplar {$copy->code} ditambahkan.");
    }

    public function edit(BookCopy $copy): View
    {
        return view('admin.copies.form', [
            'nav' => 'copies',
            'copy' => $copy,
            'books' => Book::orderBy('title')->get(['id', 'title']),
            'statuses' => BookCopyStatus::cases(),
        ]);
    }

    public function update(BookCopyRequest $request, BookCopy $copy): RedirectResponse
    {
        $copy->update($request->validated());
        $this->audit->copyUpdated($copy, $request->user());

        return redirect()->route('admin.copies.index')->with('success', "Eksemplar {$copy->code} diperbarui.");
    }

    public function destroy(BookCopy $copy): RedirectResponse
    {
        if ($copy->loans()->whereNull('returned_at')->exists()) {
            return back()->with('error', 'Eksemplar sedang dipinjam dan tidak bisa dihapus.');
        }

        $code = $copy->code;
        $copy->delete();
        $this->audit->copyDeleted($copy, request()->user());

        return back()->with('success', "Eksemplar {$code} dihapus.");
    }
}
