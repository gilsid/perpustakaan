<?php

namespace App\Services;

/**
 * Nominal denda. Sumber hari telat ada di LoanClock; kelas ini hanya mengurus
 * uang, sehingga batas grace dan cap tidak tercampur.
 */
final class FineCalculator
{
    public function __construct(private readonly int $maxAmount) {}

    public static function fromConfig(): self
    {
        return new self(maxAmount: (int) config('library.fine.max'));
    }

    /** Nominal denda, dibatasi cap maksimum. Tarif berasal dari snapshot pinjaman. */
    public function amount(int $lateDays, int $ratePerDay): int
    {
        return min(max(0, $lateDays) * max(0, $ratePerDay), $this->maxAmount);
    }
}
