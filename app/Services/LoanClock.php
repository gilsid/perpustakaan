<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Satu-satunya pemilik aturan waktu pinjaman: kapan tenggat lewat, berapa hari
 * telat setelah masa tenggang, berapa hari tersisa, dan kapan pinjaman dianggap
 * segera jatuh tempo.
 */
final class LoanClock
{
    public function __construct(
        private readonly int $graceHours,
        private readonly int $dueSoonDays,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            graceHours: (int) config('library.fine.grace_hours'),
            dueSoonDays: (int) config('library.loan.due_soon_days'),
        );
    }

    public function isOverdue(CarbonInterface $dueAt, CarbonInterface $asOf): bool
    {
        return $dueAt->lessThan($asOf);
    }

    /** Hari telat setelah masa tenggang, dibulatkan ke atas. */
    public function lateDays(CarbonInterface $dueAt, CarbonInterface $asOf): int
    {
        $lateSeconds = $asOf->getTimestamp() - $dueAt->getTimestamp() - ($this->graceHours * 3600);

        return max(0, (int) ceil($lateSeconds / 86400));
    }

    public function graceEndsAt(CarbonInterface $dueAt): CarbonInterface
    {
        return $dueAt->copy()->addHours($this->graceHours);
    }

    /** Sisa hari, dibulatkan ke atas. Nilai negatif berarti sudah lewat tenggat. */
    public function daysLeft(CarbonInterface $dueAt, CarbonInterface $asOf): int
    {
        return (int) ceil(($dueAt->getTimestamp() - $asOf->getTimestamp()) / 86400);
    }

    public function isDueSoon(CarbonInterface $dueAt, CarbonInterface $asOf): bool
    {
        return $this->daysLeft($dueAt, $asOf) <= $this->dueSoonDays;
    }

    /** Batas waktu untuk menganggap pinjaman segera jatuh tempo, dalam bentuk yang bisa dipakai SQL. */
    public function dueSoonCutoff(CarbonInterface $asOf): CarbonInterface
    {
        return $asOf->copy()->addDays($this->dueSoonDays);
    }
}
