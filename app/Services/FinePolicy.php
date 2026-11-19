<?php

namespace App\Services;

/**
 * Aturan uang dan skorsing, dirakit sekali dari config. Murni: tidak menyentuh
 * database maupun waktu sistem, jadi seluruh batasnya bisa diuji tanpa fixture.
 */
final class FinePolicy
{
    public function __construct(
        private readonly int $ratePerDay,
        private readonly int $maxAmount,
        private readonly int $suspendFactor,
        private readonly int $suspendMaxDays,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            ratePerDay: (int) config('library.fine.rate_per_day'),
            maxAmount: (int) config('library.fine.max'),
            suspendFactor: (int) config('library.fine.suspend_factor'),
            suspendMaxDays: (int) config('library.fine.suspend_max_days'),
        );
    }

    /** Tarif yang di-snapshot ke pinjaman baru. */
    public function ratePerDay(): int
    {
        return $this->ratePerDay;
    }

    /**
     * Nominal denda, dibatasi cap. Tarif berasal dari snapshot pinjaman, bukan
     * dari config, sehingga tagihan lama tidak berubah saat tarif dinaikkan.
     */
    public function amount(int $lateDays, int $ratePerDay): int
    {
        return min(max(0, $lateDays) * max(0, $ratePerDay), $this->maxAmount);
    }

    /** Lama skorsing untuk sekian hari telat, dibatasi cap. */
    public function suspensionDays(int $lateDays): int
    {
        return min(max(0, $lateDays * $this->suspendFactor), $this->suspendMaxDays);
    }
}
