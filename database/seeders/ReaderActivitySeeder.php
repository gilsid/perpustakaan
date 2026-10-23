<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReaderActivitySeeder extends Seeder
{
    /** Satu pinjaman, ulasan, dan favorit contoh dari anggota contoh. */
    public function run(): void
    {
        $member = User::where('email', 'user@example.com')->first();
        $book = Book::where('title', 'The Mystery of Cabin Island')->first();
        if (! $member || ! $book || Loan::where('user_id', $member->id)->exists()) {
            return;
        }

        $borrowed = now()->subDays(20);
        Loan::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => $borrowed,
            'due_at' => $borrowed->copy()->addDays(config('library.loan.days')),
            'returned_at' => $borrowed->copy()->addMinutes(5),
        ]);
        Review::firstOrCreate(
            ['user_id' => $member->id, 'book_id' => $book->id],
            ['rating' => 5, 'comment' => 'Buku nya sangat bagus'],
        );
        $member->favorites()->syncWithoutDetaching([$book->id]);
    }
}
