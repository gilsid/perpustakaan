<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.favorites', [
            'tab' => 'favorites',
            'books' => $request->user()->favorites()->with('category')->withStats()->orderByPivot('created_at', 'desc')->get(),
        ]);
    }
}
