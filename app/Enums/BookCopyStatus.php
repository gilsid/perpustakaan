<?php

namespace App\Enums;

/** Kondisi unit fisik. Digunakan untuk ketersediaan dan pencatatan check-in. */
enum BookCopyStatus: string
{
    case Available = 'available';
    case Loan = 'loan';
    case OnHold = 'on_hold';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Loan => 'Dipinjam',
            self::OnHold => 'Siap diambil',
            self::Lost => 'Hilang',
            self::Damaged => 'Rusak',
            self::Withdrawn => 'Tidak berlaku',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'badge--ok',
            self::Loan => 'badge--info',
            self::OnHold => 'badge--warn',
            self::Lost, self::Damaged => 'badge--danger',
            self::Withdrawn => 'badge--muted',
        };
    }

    public function isLoanable(): bool
    {
        return $this === self::Available;
    }
}
