<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use App\Services\LoanAccess;

class BookPolicy
{
    public function __construct(private readonly LoanAccess $access) {}

    /** Membaca PDF: admin selalu boleh; anggota hanya saat pinjamannya aktif. */
    public function read(User $user, Book $book): bool
    {
        if (! $book->has_pdf) {
            return false;
        }

        return $user->isAdmin() || $this->access->activeFor($user, $book) !== null;
    }
}
