<?php

namespace App\Enums;

enum CatalogSort: string
{
    case Relevance = 'relevan';
    case Newest = 'terbaru';
    case Title = 'judul';
    case Rating = 'rating';
    case Year = 'tahun';
    case Popular = 'populer';

    public function label(): string
    {
        return match ($this) {
            self::Relevance => 'Paling relevan',
            self::Newest => 'Terbaru ditambahkan',
            self::Title => 'Judul A–Z',
            self::Rating => 'Rating tertinggi',
            self::Year => 'Tahun terbit terbaru',
            self::Popular => 'Paling sering dipinjam',
        };
    }
}
