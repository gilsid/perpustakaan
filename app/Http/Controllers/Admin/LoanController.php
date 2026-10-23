<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Support\Query;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $status = Query::enum($request, 'status', LoanStatus::class);
        $q = Query::string($request, 'q');

        $loans = Loan::query()
            ->with(['user', 'book'])
            ->withStatus($status)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->whereHas('user', fn ($u) => $u->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                    ->orWhereHas('book', fn ($b) => $b->where('title', 'ilike', $like)));
            })
            ->latest('borrowed_at')->latest('id')
            ->paginate(config('library.admin_per_page'), pageName: 'halaman')->withQueryString();

        return view('admin.loans', ['nav' => 'loans', 'loans' => $loans, 'status' => $status, 'q' => $q]);
    }

    /** Mengakhiri pinjaman aktif (akses langsung ditutup). */
    public function update(Loan $loan): RedirectResponse
    {
        if ($loan->returned_at !== null) {
            return back()->with('error', 'Pinjaman tidak ditemukan atau sudah selesai.');
        }

        $loan->update(['returned_at' => now()]);
        ActivityLog::record('finish_loan', 'loan', $loan->id, "Mengakhiri pinjaman #{$loan->id} (buku #{$loan->book_id}, anggota #{$loan->user_id})");

        return back()->with('success', 'Pinjaman diakhiri.');
    }
}
