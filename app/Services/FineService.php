<?php

namespace App\Services;

use App\Exceptions\LoanException;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Siklus denda: menilai tagihan saat pinjaman ditutup, menagih total yang
 * terbuka, melunasi, membebaskan, dan mengurus skorsing yang menyertainya.
 */
final class FineService
{
    public function __construct(
        private readonly FinePolicy $policy,
        private readonly LoanClock $clock,
    ) {}

    /**
     * Mengisi hasil penilaian pada pinjaman yang baru ditutup dan menerapkan
     * skorsing bila telat. Pencatatan log tetap milik pemanggil.
     */
    public function assess(Loan $loan, CarbonInterface $returnedAt): Loan
    {
        $lateDays = $this->clock->lateDays($loan->due_at, $returnedAt);
        $amount = $this->policy->amount($lateDays, (int) $loan->fine_rate);

        $loan->update([
            'returned_at' => $returnedAt,
            'late_days' => $lateDays,
            'fine_amount' => $amount,
        ]);

        if ($lateDays > 0) {
            $this->suspend($loan->user()->firstOrFail(), $lateDays);
        }

        return $loan->refresh();
    }

    public function unpaidTotal(User $user): int
    {
        return (int) $user->loans()->unpaidFine()->sum('fine_amount');
    }

    /**
     * @throws LoanException
     */
    public function settle(Loan $loan): void
    {
        if (! $loan->fine_status->canBeSettled()) {
            throw new LoanException('Tidak ada denda yang perlu dilunasi.');
        }

        $loan->update(['fine_paid_at' => now()]);
    }

    /**
     * Membebaskan denda sekaligus mencabut skorsing: alasan yang sah untuk
     * membebaskan denda juga alasan sah membatalkan skorsing.
     *
     * @throws LoanException
     */
    public function waive(Loan $loan, User $admin): void
    {
        if (! $loan->fine_status->canBeWaived()) {
            throw new LoanException('Tidak ada denda yang bisa dibebaskan.');
        }

        $loan->update([
            'fine_waived_at' => now(),
            'fine_waived_by' => $admin->id,
        ]);

        User::query()->whereKey($loan->user_id)->update(['suspend_until' => null]);
    }

    private function suspend(User $user, int $lateDays): void
    {
        $days = $this->policy->suspensionDays($lateDays);

        if ($days <= 0) {
            return;
        }

        $until = now()->addDays($days);

        // Baca ulang dengan kunci: dua pengembalian telat yang hampir bersamaan
        // tidak boleh saling menimpa dan memendekkan skorsing.
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $current = $locked->suspend_until;

        $locked->forceFill([
            'suspend_until' => $current !== null && $current->greaterThan($until) ? $current : $until,
        ])->save();
    }
}
