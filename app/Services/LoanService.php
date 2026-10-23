<?php

namespace App\Services;

use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * @throws LoanException
     */
    public function borrow(User $user, Book $book): Loan
    {
        if (! $book->has_pdf) {
            throw new LoanException('Buku ini belum tersedia dalam bentuk digital, jadi belum bisa dipinjam.');
        }

        $days = (int) config('library.loan.days');
        $max = (int) config('library.loan.max_active');

        try {
            return DB::transaction(function () use ($user, $book, $days, $max): Loan {
                // Kunci baris anggota agar dua permintaan bersamaan tidak sama-sama lolos batas pinjaman.
                User::query()->whereKey($user->id)->lockForUpdate()->first();

                // Tutup pinjaman lama yang sudah berakhir agar buku yang sama bisa dipinjam ulang.
                Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->expired()
                    ->update(['returned_at' => DB::raw('due_at')]);

                if (Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->active()->exists()) {
                    throw new LoanException('Anda sudah meminjam buku ini.');
                }
                if (Loan::query()->where('user_id', $user->id)->active()->count() >= $max) {
                    throw new LoanException("Batas pinjaman tercapai ($max buku). Kembalikan salah satu buku dulu.");
                }

                return Loan::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'borrowed_at' => now(),
                    'due_at' => now()->addDays($days),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new LoanException('Anda sudah meminjam buku ini.');
        }
    }

    /**
     * @throws LoanException
     */
    public function giveBack(User $user, Book $book): void
    {
        $returned = Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)
            ->whereNull('returned_at')->update(['returned_at' => now()]);

        if ($returned === 0) {
            throw new LoanException('Tidak ada pinjaman aktif untuk buku ini.');
        }
    }

    /**
     * @throws LoanException
     */
    public function renew(User $user, Book $book): Loan
    {
        $loan = Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->active()->first()
            ?? throw new LoanException('Tidak ada pinjaman aktif untuk diperpanjang.');

        $max = (int) config('library.loan.max_renewals');
        if (! $loan->can_renew) {
            throw new LoanException($max === 0 ? 'Perpanjangan tidak tersedia.' : "Pinjaman hanya bisa diperpanjang $max kali.");
        }

        $loan->update([
            'due_at' => $loan->due_at->copy()->addDays((int) config('library.loan.days')),
            'renewals' => $loan->renewals + 1,
        ]);

        return $loan;
    }
}
