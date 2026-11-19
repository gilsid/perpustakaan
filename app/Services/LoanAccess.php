<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

/**
 * Pertanyaan tentang hubungan satu anggota dengan satu buku. Halaman buku,
 * pembaca PDF, policy akses, dan perpanjangan memakai definisi yang sama di
 * sini, jadi arti "pinjaman aktif" hanya diubah di satu tempat.
 */
final class LoanAccess
{
    /** Pinjaman berjalan yang masih memberi akses baca. */
    public function activeFor(User $user, Book $book): ?Loan
    {
        return Loan::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->first();
    }

    /** Pernah meminjam buku ini, termasuk yang sudah dikembalikan. */
    public function hasBorrowed(User $user, Book $book): bool
    {
        return Loan::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->exists();
    }
}
