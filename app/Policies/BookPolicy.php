<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

class BookPolicy
{
    /** Membaca PDF: admin selalu boleh; anggota hanya saat pinjamannya aktif. */
    public function read(User $user, Book $book): bool
    {
        if (! $book->has_pdf) {
            return false;
        }

        return $user->isAdmin()
            || Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->active()->exists();
    }
}
