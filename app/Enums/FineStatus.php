<?php

namespace App\Enums;

/**
 * Status denda diturunkan dari kolom, bukan disimpan:
 *  - None   : pinjaman tidak menghasilkan denda
 *  - Unpaid : ada tagihan yang belum dilunasi dan belum dibebaskan
 *  - Paid   : tagihan sudah dilunasi
 *  - Waived : tagihan dibebaskan admin (nominal asli tetap tercatat untuk audit)
 */
enum FineStatus: string
{
    case None = 'tidak_ada';
    case Unpaid = 'belum_lunas';
    case Paid = 'lunas';
    case Waived = 'dibebaskan';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa denda',
            self::Unpaid => 'Belum lunas',
            self::Paid => 'Lunas',
            self::Waived => 'Dibebaskan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'badge--muted',
            self::Unpaid => 'badge--danger',
            self::Paid => 'badge--ok',
            self::Waived => 'badge--info',
        };
    }
}
