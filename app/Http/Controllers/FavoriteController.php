<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function store(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favorites()->syncWithoutDetaching([$book->id]);

        return back()->with('success', 'Disimpan ke rak favorit.');
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favorites()->detach($book->id);

        return back()->with('success', 'Dihapus dari rak favorit.');
    }
}
