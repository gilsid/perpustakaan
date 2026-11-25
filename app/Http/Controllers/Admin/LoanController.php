<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookCopyCondition;
use App\Enums\LoanStatus;
use App\Exceptions\LoanException;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use App\Support\Query;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(Request $request): View
    {
        $status = Query::enum($request, 'status', LoanStatus::class);
        $search = SearchTerm::fromRequest($request);
        $fineOnly = $request->boolean('denda');

        $loans = Loan::query()
            ->with(['user', 'book', 'copy', 'fineWaivedBy'])
            ->withStatus($status)
            ->when($fineOnly, fn ($query) => $query->unpaidFine())
            ->when(! $search->isBlank(), function ($query) use ($search) {
                $like = $search->like();
                $query->where(fn ($w) => $w
                    ->whereHas('user', fn ($u) => $u->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                    ->orWhereHas('book', fn ($b) => $b->where('title', 'ilike', $like)));
            })
            ->latest('borrowed_at')->latest('id')
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.loans', [
            'nav' => 'loans',
            'loans' => $loans,
            'status' => $status,
            'q' => $search->value,
            'fineOnly' => $fineOnly,
        ]);
    }

    public function renew(Request $request, Loan $loan): RedirectResponse
    {
        try {
            $renewed = $this->loans->renewByStaff($loan, $request->user());
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pinjaman diperpanjang sampai '.$renewed->due_at->translatedFormat('j M Y').'.');
    }

    public function update(Request $request, Loan $loan): RedirectResponse
    {
        $condition = null;
        if ($loan->isPhysical()) {
            $validated = $request->validate([
                'condition' => ['required', Rule::enum(BookCopyCondition::class)],
            ], ['condition.required' => 'Kondisi eksemplar wajib dipilih.']);
            $condition = BookCopyCondition::from($validated['condition']);
        }

        try {
            $closure = $this->loans->endByAdmin($loan, $request->user(), $condition);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $closure->adminMessage());
    }
}
