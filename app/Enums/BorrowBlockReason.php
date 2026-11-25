<?php

namespace App\Enums;

/**
 * Alasan anggota belum bisa meminjam. Urutan kemunculan diatur
 * BorrowEligibility; enum ini hanya memberi nama alasan.
 */
enum BorrowBlockReason: string
{
    case NoPdf = 'tanpa_pdf';
    case NoMemberNumber = 'tanpa_nomor_kartu';
    case InactiveOrNotMember = 'bukan_anggota_aktif';
    case MembershipExpired = 'keanggotaan_berakhir';
    case CopyUnavailable = 'eksemplar_tidak_tersedia';
    case AlreadyBorrowed = 'sudah_dipinjam';
    case Suspended = 'diskors';
    case UnpaidFine = 'denda_belum_lunas';
    case OverdueLoan = 'pinjaman_terlambat';
    case QuotaReached = 'kuota_penuh';
}
