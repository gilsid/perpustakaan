<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatalogSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookMedia;
use App\Support\CatalogFilters;
use App\Support\Query;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function __construct(private readonly BookMedia $media) {}

    public function index(Request $request): View
    {
        $filters = new CatalogFilters(q: Query::string($request, 'q'), category: Query::string($request, 'kategori'));

        return view('admin.books.index', [
            'nav' => 'books',
            'filters' => $filters,
            'books' => Book::query()->with('category')->withStats()->filter($filters)
                ->sortedBy($filters->q !== '' ? CatalogSort::Relevance : CatalogSort::Newest, $filters->q)
                ->paginate(15, pageName: 'halaman')->withQueryString(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Book);
    }

    public function edit(Book $book): View
    {
        return $this->form($book->load('category'));
    }

    private function form(Book $book): View
    {
        return view('admin.books.form', [
            'nav' => 'books',
            'book' => $book,
            'categories' => Category::orderBy('name')->get(),
            'languages' => collect(['Indonesia', 'Inggris', 'Arab', 'Jawa'])
                ->merge(Book::query()->whereNotNull('language')->distinct()->pluck('language'))->unique()->values(),
            'limits' => [
                'cover' => round(config('library.uploads.cover_max_kb') / 1024),
                'pdf' => round(config('library.uploads.pdf_max_kb') / 1024),
            ],
        ]);
    }

    public function store(BookRequest $request): RedirectResponse
    {
        $book = Book::create($this->attributes($request) + $this->files($request, new Book));
        ActivityLog::record('create_book', 'book', $book->id, "Menambah buku \"{$book->title}\"");

        return redirect()->route('admin.books.index')->with('success', 'Buku ditambahkan.');
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $oldCover = $book->cover_path;
        $oldPdf = $book->pdf_path;
        $files = $this->files($request, $book);

        $book->update($this->attributes($request) + $files);

        // Berkas lama dihapus hanya setelah data tersimpan.
        if (array_key_exists('cover_path', $files) && $oldCover !== $book->cover_path) {
            $this->media->deleteCover($oldCover);
        }
        if (array_key_exists('pdf_path', $files) && $oldPdf !== $book->pdf_path) {
            $this->media->deletePdf($oldPdf);
        }
        ActivityLog::record('update_book', 'book', $book->id, "Mengubah buku \"{$book->title}\"");

        return redirect()->route('admin.books.index')->with('success', 'Perubahan buku disimpan.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $book->delete();
        $this->media->deleteCover($book->cover_path);
        $this->media->deletePdf($book->pdf_path);
        ActivityLog::record('delete_book', 'book', $book->id, "Menghapus buku \"{$book->title}\"");

        return redirect()->route('admin.books.index')
            ->with('success', "Buku \"{$book->title}\" dihapus beserta berkas dan ulasannya.");
    }

    /** @return array<string, mixed> */
    private function attributes(BookRequest $request): array
    {
        return collect($request->validated())
            ->only(['title', 'author', 'category_id', 'call_number', 'isbn', 'publisher', 'published_year', 'pages', 'language'])
            ->map(fn ($v) => $v === '' ? null : $v)
            ->put('description', (string) $request->input('description'))
            ->all();
    }

    /**
     * Berkas baru/dihapus. Hanya kunci yang berubah yang dikembalikan.
     *
     * @return array<string, ?string>
     */
    private function files(BookRequest $request, Book $book): array
    {
        $out = [];
        if ($request->hasFile('cover')) {
            $out['cover_path'] = $this->media->storeCover($request->file('cover'));
        } elseif ($book->exists && $request->boolean('remove_cover')) {
            $out['cover_path'] = null;
        }
        if ($request->hasFile('pdf')) {
            $out['pdf_path'] = $this->media->storePdf($request->file('pdf'));
        } elseif ($book->exists && $request->boolean('remove_pdf')) {
            $out['pdf_path'] = null;
        }

        return $out;
    }
}
