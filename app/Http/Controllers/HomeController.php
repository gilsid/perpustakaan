<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'newest' => Book::query()->with('category')->withStats()->latest()->orderByDesc('id')->limit(6)->get(),
            'topRated' => Book::query()->with('category')->withStats()->has('reviews')
                ->orderByRaw('avg_rating DESC NULLS LAST')->orderByDesc('reviews_count')->orderBy('title')->limit(6)->get(),
            'categories' => Category::query()->withCount('books')->has('books')->orderBy('name')->get(),
            'stats' => [
                'titles' => Book::count(),
                'digital' => Book::whereNotNull('pdf_path')->count(),
            ],
        ]);
    }
}
