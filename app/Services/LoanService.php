<?php

namespace App\Services;

use App\Exceptions\LoanException;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(
        private readonly FineService $fines,
        private readonly FinePolicy $finePolicy,
        private readonly LoanAccess $access,
        private readonly BorrowEligibility $eligibility,
    ) {}

    /**
     * @throws LoanException
     */
    public function borrow(User $user, Book $book): Loan
    {
        $days = (int) config('library.loan.days');
        $rate = $this->finePolicy->ratePerDay();

        try {
            return DB::transaction(function () use ($user, $book, $days, $rate): Loan {
                // Kunci baris anggota agar dua permintaan bersamaan tidak sama-sama lolos batas pinjaman.
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($block = $this->eligibility->forBook($locked, $book)->first()) {
                    throw new LoanException($block->message());
                }

                return Loan::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'borrowed_at' => now(),
                    'due_at' => now()->addDays($days),
                    'fine_rate' => $rate,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new LoanException('Anda sudah meminjam buku ini.');
        }
    }

    /**
     * Mengembalikan buku dan menetapkan denda bila melewati tenggat.
     *
     * @throws LoanException
     */
    public function giveBack(User $user, Book $book): Loan
    {
        return DB::transaction(function () use ($user, $book): Loan {
            // Kunci anggota lebih dulu (urutan sama dengan borrow) agar pengembalian dan
            // peminjaman bersamaan tidak saling mengunci terbalik.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $loan = Loan::query()
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->whereNull('returned_at')
                ->lockForUpdate()
                ->first()
                ?? throw new LoanException('Tidak ada pinjaman aktif untuk buku ini.');

            return $this->closeLoan($loan, $user);
        });
    }

    /**
     * Mengakhiri pinjaman dari panel admin. Denda tetap dihitung bila sudah lewat tenggat.
     *
     * @throws LoanException
     */
    public function endByAdmin(Loan $loan, User $admin): Loan
    {
        return DB::transaction(function () use ($loan, $admin): Loan {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            if ($locked->returned_at !== null) {
                throw new LoanException('Pinjaman tidak ditemukan atau sudah selesai.');
            }

            $closed = $this->closeLoan($locked, $admin);

            ActivityLog::record('finish_loan', 'loan', $closed->id, "Mengakhiri pinjaman #{$closed->id} (buku #{$closed->book_id}, anggota #{$closed->user_id})", $admin);

            return $closed;
        });
    }

    /**
     * @throws LoanException
     */
    public function renew(User $user, Book $book): Loan
    {
        $loan = $this->access->activeFor($user, $book)
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

    /** @throws LoanException */
    public function settleFine(Loan $loan, User $admin): void
    {
        DB::transaction(function () use ($loan, $admin): void {
            // Kunci baris agar dua admin yang menekan "Lunas" bersamaan tidak
            // sama-sama lolos dan mencatat log ganda.
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->settle($locked);

            ActivityLog::record(
                'fine_paid',
                'loan',
                $locked->id,
                'Menandai lunas denda '.Rupiah::format($locked->fine_amount)." pinjaman #{$locked->id} (anggota #{$locked->user_id})",
                $admin,
            );
        });
    }

    /**
     * Membebaskan denda sekaligus mencabut skorsing: alasan yang sah untuk membebaskan
     * denda (sakit, force majeure, kesalahan sistem) juga alasan sah membatalkan skorsing.
     *
     * @throws LoanException
     */
    public function waiveFine(Loan $loan, User $admin, string $reason): void
    {
        DB::transaction(function () use ($loan, $admin, $reason): void {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->waive($locked, $admin);

            ActivityLog::record(
                'fine_waived',
                'loan',
                $locked->id,
                'Membebaskan denda '.Rupiah::format($locked->fine_amount)." pinjaman #{$locked->id} (anggota #{$locked->user_id}): {$reason}",
                $admin,
            );
        });
    }

    /**
     * Menutup pinjaman: hitung denda, snapshot hasilnya, lalu terapkan skorsing.
     *
     * @throws LoanException
     */
    private function closeLoan(Loan $loan, User $actor): Loan
    {
        $closed = $this->fines->assess($loan, now());

        if ($closed->late_days > 0) {
            ActivityLog::record(
                'fine_assessed',
                'loan',
                $closed->id,
                $closed->fine_amount > 0
                    ? "Denda {$closed->late_days} hari telat = ".Rupiah::format($closed->fine_amount)." untuk pinjaman #{$closed->id} (anggota #{$closed->user_id})"
                    : "Keterlambatan {$closed->late_days} hari tanpa denda (tarif 0) untuk pinjaman #{$closed->id} (anggota #{$closed->user_id})",
                $actor,
            );
        }

        return $closed;
    }
}
