<?php

namespace App\Enums;

enum BookCopyCondition: string
{
    case Good = 'baik';
    case Damaged = 'rusak';
    case Lost = 'hilang';

    public function copyStatus(): BookCopyStatus
    {
        return match ($this) {
            self::Good => BookCopyStatus::Available,
            self::Damaged => BookCopyStatus::Damaged,
            self::Lost => BookCopyStatus::Lost,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
        };
    }
}
