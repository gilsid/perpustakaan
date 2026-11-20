<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LoanException;
use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CirculationController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(): View
    {
        return view('admin.circulation', [
            'recent' => Loan::query()
                ->with(['user', 'book', 'copy'])
                ->where('format', 'fisik')
                ->latest('borrowed_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member' => ['required', 'string', 'max:100'],
            'barcode' => ['required', 'string', 'max:40'],
        ], [
            'member.required' => 'Nomor kartu anggota wajib diisi.',
            'barcode.required' => 'Barcode eksemplar wajib diisi.',
        ]);

        $user = User::query()
            ->where('member_number', $validated['member'])
            ->orWhere('email', $validated['member'])
            ->first();

        if (! $user) {
            return back()->with('error', 'Anggota dengan nomor kartu itu tidak ditemukan.')->withInput();
        }

        $copy = BookCopy::query()->where('code', $validated['barcode'])->first();

        if (! $copy) {
            return back()->with('error', 'Eksemplar dengan barcode itu tidak ditemukan.')->withInput();
        }

        try {
            $loan = $this->loans->checkOutCopy($user, $copy, $request->user());
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Eksemplar '.$copy->code.' dipinjamkan kepada '.$user->name.'. Kembali '.$loan->due_at->translatedFormat('j M Y').'.');
    }
}
