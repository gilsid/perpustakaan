<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(Request $request): View
    {
        $loans = Loan::query()->where('user_id', $request->user()->id)->with('book.category');

        return view('account.loans', [
            'tab' => 'loans',
            'active' => (clone $loans)->active()->orderBy('due_at')->get(),
            'overdue' => (clone $loans)->overdue()->orderBy('due_at')->get(),
            'history' => (clone $loans)->returned()->latest('borrowed_at')->limit(100)->get(),
            'blockReasons' => $this->loans->blockReasons($request->user()),
        ]);
    }
}
