<?php

namespace Database\Seeders;

use App\Enums\BookCopyStatus;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Database\Seeder;

class BookCopySeeder extends Seeder
{
    /** Dua eksemplar fisik untuk lima buku pertama. Aman dijalankan ulang. */
    public function run(): void
    {
        Book::query()->orderBy('id')->limit(5)->get()->each(function (Book $book, int $index): void {
            foreach (range(1, 2) as $number) {
                BookCopy::firstOrCreate(
                    ['code' => sprintf('BK-%04d-%02d', $book->id, $number)],
                    [
                        'book_id' => $book->id,
                        'location' => 'Rak '.chr(65 + $index),
                        'status' => BookCopyStatus::Available,
                    ],
                );
            }
        });
    }
}
