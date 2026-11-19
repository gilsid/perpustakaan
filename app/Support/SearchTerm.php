<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Kata kunci pencarian yang sudah dibersihkan dari URL, plus pola ILIKE yang
 * aman dipakai di SQL. Karakter %, _, dan \ yang diketik pengguna diperlakukan
 * sebagai huruf biasa, bukan wildcard.
 */
final readonly class SearchTerm
{
    public function __construct(public string $value) {}

    public static function fromRequest(Request $request, string $key = 'q'): self
    {
        return new self(Query::string($request, $key));
    }

    public function isBlank(): bool
    {
        return $this->value === '';
    }

    /** Pola yang cocok di mana pun. */
    public function like(): string
    {
        return '%'.$this->escape().'%';
    }

    /** Pola yang hanya cocok di awal, untuk peringkat relevansi. */
    public function startsWith(): string
    {
        return $this->escape().'%';
    }

    private function escape(): string
    {
        return addcslashes($this->value, '%_\\');
    }
}
