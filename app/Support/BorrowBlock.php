<?php

namespace App\Support;

use App\Enums\BorrowBlockReason;
use Carbon\CarbonInterface;

/**
 * Satu alasan peminjaman ditolak: kode terketik plus data yang dibutuhkan
 * untuk kalimatnya.
 */
final readonly class BorrowBlock
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(public BorrowBlockReason $reason, public array $data = []) {}

    public static function noPdf(): self
    {
        return new self(BorrowBlockReason::NoPdf);
    }

    public static function noMemberNumber(): self
    {
        return new self(BorrowBlockReason::NoMemberNumber);
    }

    public static function inactiveOrNotMember(): self
    {
        return new self(BorrowBlockReason::InactiveOrNotMember);
    }

    public static function membershipExpired(CarbonInterface $until): self
    {
        return new self(BorrowBlockReason::MembershipExpired, ['until' => $until]);
    }

    public static function copyUnavailable(): self
    {
        return new self(BorrowBlockReason::CopyUnavailable);
    }

    public static function alreadyBorrowed(): self
    {
        return new self(BorrowBlockReason::AlreadyBorrowed);
    }

    public static function suspended(CarbonInterface $until): self
    {
        return new self(BorrowBlockReason::Suspended, ['until' => $until]);
    }

    public static function unpaidFine(int $total): self
    {
        return new self(BorrowBlockReason::UnpaidFine, ['total' => $total]);
    }

    /**
     * @param  list<string>  $titles
     */
    public static function overdueLoans(array $titles): self
    {
        return new self(BorrowBlockReason::OverdueLoan, ['titles' => $titles]);
    }

    public static function quotaReached(int $used, int $max): self
    {
        return new self(BorrowBlockReason::QuotaReached, ['used' => $used, 'max' => $max]);
    }

    public function message(): string
    {
        return match ($this->reason) {
            BorrowBlockReason::NoPdf => 'Buku ini belum tersedia dalam bentuk digital, jadi belum bisa dipinjam.',
            BorrowBlockReason::NoMemberNumber => 'Akun ini belum memiliki nomor kartu anggota.',
            BorrowBlockReason::InactiveOrNotMember => 'Hanya anggota aktif yang boleh meminjam.',
            BorrowBlockReason::MembershipExpired => 'Keanggotaan berakhir pada '.$this->data['until']->translatedFormat('j M Y').'. Perbarui dulu di perpustakaan.',
            BorrowBlockReason::CopyUnavailable => 'Eksemplar fisik ini sedang dipinjam, ditahan untuk hold, atau tidak tersedia.',
            BorrowBlockReason::AlreadyBorrowed => 'Anda sudah meminjam buku ini.',
            BorrowBlockReason::Suspended => 'Akun Anda diskors sampai '.$this->data['until']->translatedFormat('j M Y').' karena keterlambatan pengembalian.',
            BorrowBlockReason::UnpaidFine => 'Ada denda belum lunas sebesar '.Rupiah::format($this->data['total']).'. Selesaikan ke pustakawan sebelum meminjam lagi.',
            BorrowBlockReason::OverdueLoan => 'Masih ada pinjaman lewat tenggat: “'.implode('”, “', $this->data['titles']).'”. Kembalikan dulu.',
            BorrowBlockReason::QuotaReached => 'Batas pinjaman tercapai ('.$this->data['max'].' buku). Kembalikan salah satu buku dulu.',
        };
    }
}
