<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Perhitungan denda keterlambatan. Murni: tidak menyentuh database maupun waktu sistem,
 * sehingga seluruh aturan bisa diuji tanpa fixture.
 */
final class FineCalculator
{
    public function __construct(
        private readonly int $graceHours,
        private readonly int $maxAmount,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            graceHours: (int) config('library.fine.grace_hours'),
            maxAmount: (int) config('library.fine.max'),
        );
    }

    /** Jumlah hari telat (dibulatkan ke atas) setelah dikurangi masa tenggang. */
    public function lateDays(CarbonInterface $dueAt, CarbonInterface $returnedAt): int
    {
        $lateSeconds = $returnedAt->getTimestamp() - $dueAt->getTimestamp() - ($this->graceHours * 3600);

        return max(0, (int) ceil($lateSeconds / 86400));
    }

    /** Nominal denda, dibatasi cap maksimum. Tarif berasal dari snapshot pinjaman. */
    public function amount(int $lateDays, int $ratePerDay): int
    {
        return min(max(0, $lateDays) * max(0, $ratePerDay), $this->maxAmount);
    }
}
