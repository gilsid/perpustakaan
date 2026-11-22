<?php

namespace App\Http\Controllers;

use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\Hold;
use App\Services\HoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    public function __construct(private readonly HoldService $holds) {}

    public function store(Request $request, Book $book): RedirectResponse
    {
        try {
            $this->holds->place($request->user(), $book);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hold dipasang. Eksemplar berikutnya akan ditahan untuk Anda.');
    }

    public function destroy(Request $request, Hold $hold): RedirectResponse
    {
        try {
            $this->holds->cancel($hold, $request->user());
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hold dibatalkan.');
    }
}
