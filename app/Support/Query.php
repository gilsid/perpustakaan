<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Http\Request;

/**
 * Pembaca parameter query yang toleran: nilai aneh (array, teks non-angka) diabaikan, bukan menyebabkan galat.
 * Dipakai untuk filter pada URL GET yang bisa diketik siapa saja.
 */
final class Query
{
    public static function string(Request $request, string $key, int $max = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    public static function int(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_string($value) && preg_match('/^-?\d+$/', trim($value)) ? (int) trim($value) : null;
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public static function enum(Request $request, string $key, string $enum): ?BackedEnum
    {
        $value = $request->query($key);

        return is_string($value) ? $enum::tryFrom($value) : null;
    }
}
