<?php

namespace App\Enums;

/**
 * Status pinjaman diturunkan dari kolom, bukan disimpan:
 *  - Active   : belum dikembalikan dan tenggat di masa depan
 *  - Expired  : belum dikembalikan tetapi tenggat sudah lewat (akses otomatis tertutup)
 *  - Returned : sudah dikembalikan
 */
enum LoanStatus: string
{
    case Active = 'aktif';
    case Expired = 'berakhir';
    case Returned = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Expired => 'Berakhir',
            self::Returned => 'Dikembalikan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge--ok',
            self::Expired => 'badge--warn',
            self::Returned => 'badge--muted',
        };
    }
}
