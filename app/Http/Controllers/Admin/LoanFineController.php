<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LoanException;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanFineController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    /** Menandai denda sebagai lunas (pembayaran tunai di luar aplikasi). */
    public function pay(Request $request, Loan $loan): RedirectResponse
    {
        try {
            $this->loans->settleFine($loan, $request->user());
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Denda '.Rupiah::format($loan->fine_amount).' ditandai lunas.');
    }

    /** Membebaskan denda sekaligus mencabut skorsing. Alasan wajib agar jejak audit jelas. */
    public function waive(Request $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Alasan pembebasan wajib diisi.',
            'reason.min' => 'Alasan pembebasan minimal 5 karakter.',
        ]);

        try {
            $this->loans->waiveFine($loan, $request->user(), $validated['reason']);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Denda dibebaskan dan skorsing anggota dicabut.');
    }

    public function payReplacement(Request $request, Loan $loan): RedirectResponse
    {
        try {
            $this->loans->settleReplacement($loan, $request->user());
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Biaya penggantian '.Rupiah::format($loan->replacement_amount).' ditandai lunas.');
    }

    public function waiveReplacement(Request $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Alasan pembebasan wajib diisi.',
            'reason.min' => 'Alasan pembebasan minimal 5 karakter.',
        ]);

        try {
            $this->loans->waiveReplacement($loan, $request->user(), $validated['reason']);
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Biaya penggantian dibebaskan.');
    }
}
