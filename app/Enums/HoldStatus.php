<?php

namespace App\Enums;

enum HoldStatus: string
{
    case Active = 'aktif';
    case Ready = 'siap_diambil';
    case Fulfilled = 'dipenuhi';
    case Expired = 'kedaluwarsa';
    case Cancelled = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Menunggu eksemplar',
            self::Ready => 'Siap diambil',
            self::Fulfilled => 'Dipenuhi',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge--info',
            self::Ready => 'badge--ok',
            self::Fulfilled => 'badge--muted',
            self::Expired, self::Cancelled => 'badge--danger',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Active || $this === self::Ready;
    }
}
