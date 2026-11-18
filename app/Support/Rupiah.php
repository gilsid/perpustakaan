<?php

namespace App\Support;

/** Pemformat nominal rupiah. Nominal selalu bilangan bulat, tidak pernah float. */
final class Rupiah
{
    public static function format(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
