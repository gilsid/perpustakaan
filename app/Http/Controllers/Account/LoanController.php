<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $loans = Loan::query()->where('user_id', $request->user()->id)->with('book.category');

        return view('account.loans', [
            'tab' => 'loans',
            'active' => (clone $loans)->active()->orderBy('due_at')->get(),
            'history' => (clone $loans)->finished()->latest('borrowed_at')->limit(100)->get(),
        ]);
    }
}
