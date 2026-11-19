<?php

namespace App\Support;

use App\Models\Loan;
use Carbon\CarbonInterface;

/**
 * Hasil penutupan pinjaman: pinjaman yang sudah diperbarui dan akhir skorsing
 * anggotanya. Kalimat untuk anggota dan admin dibentuk di sini, jadi aturan
 * denda tidak perlu tahu kata-kata yang dipakai layar.
 */
final readonly class LoanClosure
{
    public function __construct(
        public Loan $loan,
        public ?CarbonInterface $suspendedUntil,
    ) {}

    /** Pesan untuk anggota yang mengembalikan bukunya. */
    public function memberMessage(): string
    {
        $message = 'Buku dikembalikan.';

        // Denda bisa nol walau telat (mis. tarif 0), jadi pesan mengikuti hari
        // telat, bukan nominal; skorsing tetap perlu diberitahukan.
        if ($this->loan->late_days > 0) {
            if ($this->loan->fine_amount > 0) {
                $message .= ' Denda '.Rupiah::format($this->loan->fine_amount)." ({$this->loan->late_days} hari telat).";
            }

            if ($this->suspendedUntil !== null && $this->suspendedUntil->isFuture()) {
                $message .= ' Akun diskors sampai '.$this->suspendedUntil->translatedFormat('j M Y').'.';
            }
        }

        return $message;
    }

    /** Pesan untuk admin yang mengakhiri pinjaman dari panel. */
    public function adminMessage(): string
    {
        return $this->loan->fine_amount > 0
            ? 'Pinjaman diakhiri. Denda '.Rupiah::format($this->loan->fine_amount)." ({$this->loan->late_days} hari telat) dicatat."
            : 'Pinjaman diakhiri.';
    }
}
