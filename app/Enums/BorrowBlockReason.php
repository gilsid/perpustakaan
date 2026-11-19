<?php

namespace App\Enums;

/**
 * Alasan anggota belum bisa meminjam. Urutan kemunculan diatur
 * BorrowEligibility; enum ini hanya memberi nama alasan.
 */
enum BorrowBlockReason: string
{
    case NoPdf = 'tanpa_pdf';
    case AlreadyBorrowed = 'sudah_dipinjam';
    case Suspended = 'diskors';
    case UnpaidFine = 'denda_belum_lunas';
    case OverdueLoan = 'pinjaman_terlambat';
    case QuotaReached = 'kuota_penuh';
}
