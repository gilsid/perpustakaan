<?php

namespace App\Enums;

/** Format pinjaman: berkas digital atau eksemplar fisik. */
enum LoanFormat: string
{
    case Digital = 'digital';
    case Physical = 'fisik';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Digital',
            self::Physical => 'Fisik',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Digital => 'badge--info',
            self::Physical => 'badge--muted',
        };
    }
}
