<?php

namespace App\Http\Controllers;

use App\Enums\CatalogSort;
use App\Models\Book;
use App\Models\Category;
use App\Support\CatalogFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = CatalogFilters::fromRequest($request);

        $books = Book::query()
            ->with('category')
            ->withStats()
            ->filter($filters)
            ->sortedBy($filters->sort, $filters->q)
            ->paginate(config('library.per_page'), pageName: 'halaman')
            ->withQueryString();

        // Jumlah per kategori mengikuti filter lain (selain kategori itu sendiri).
        $categoryCounts = Category::query()
            ->withCount(['books' => fn ($q) => $q->filter($filters, withCategory: false)])
            ->orderBy('name')
            ->get();

        $current = $filters->category !== '' ? Category::firstWhere('slug', $filters->category) : null;

        return view('catalog.index', [
            'books' => $books,
            'filters' => $filters,
            'categoryCounts' => $categoryCounts,
            'languages' => Book::query()->whereNotNull('language')->where('language', '<>', '')->distinct()->orderBy('language')->pluck('language'),
            'currentCategory' => $current,
            'sorts' => collect(CatalogSort::cases())->reject(fn ($s) => $s === CatalogSort::Relevance && $filters->q === ''),
        ]);
    }
}
