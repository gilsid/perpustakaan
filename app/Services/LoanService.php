<?php

namespace App\Services;

use App\Enums\FineStatus;
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
        private readonly FineCalculator $fines,
        private readonly LoanClock $clock,
    ) {}

    /**
     * @throws LoanException
     */
    public function borrow(User $user, Book $book): Loan
    {
        if (! $book->has_pdf) {
            throw new LoanException('Buku ini belum tersedia dalam bentuk digital, jadi belum bisa dipinjam.');
        }

        $this->ensureEligible($user);

        $days = (int) config('library.loan.days');
        $max = (int) config('library.loan.max_active');
        $rate = (int) config('library.fine.rate_per_day');

        try {
            return DB::transaction(function () use ($user, $book, $days, $max, $rate): Loan {
                // Kunci baris anggota agar dua permintaan bersamaan tidak sama-sama lolos batas pinjaman.
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $this->ensureEligible($locked);

                // Pinjaman lewat tenggat sengaja dibiarkan terbuka: itu bukti keterlambatan
                // dan dasar denda. Karena itu buku yang sama tidak bisa dipinjam ulang.
                if (Loan::query()->where('user_id', $user->id)->where('book_id', $book->id)->whereNull('returned_at')->exists()) {
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

    /**
     * Alasan anggota tidak boleh meminjam buku baru. Kosong berarti boleh.
     *
     * @return list<string>
     */
    public function blockReasons(User $user): array
    {
        $reasons = [];

        if ($user->isSuspended()) {
            $reasons[] = 'Akun Anda diskors sampai '.$user->suspend_until->translatedFormat('j M Y').' karena keterlambatan pengembalian.';
        }

        $fines = $user->unpaidFineTotal();
        if ($fines > 0) {
            $reasons[] = 'Ada denda belum lunas sebesar '.Rupiah::format($fines).'. Selesaikan ke pustakawan sebelum meminjam lagi.';
        }

        $overdue = Loan::query()->where('user_id', $user->id)->overdue()->with('book')->orderBy('due_at')->get();
        if ($overdue->isNotEmpty()) {
            $titles = $overdue->take(2)->pluck('book.title')->implode('”, “');
            $reasons[] = 'Masih ada pinjaman lewat tenggat: “'.$titles.'”. Kembalikan dulu.';
        }

        return $reasons;
    }

    /** @throws LoanException */
    public function settleFine(Loan $loan, User $admin): void
    {
        DB::transaction(function () use ($loan, $admin): void {
            // Kunci baris agar dua admin yang menekan "Lunas" bersamaan tidak
            // sama-sama lolos dan mencatat log ganda.
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            if ($locked->fine_status !== FineStatus::Unpaid) {
                throw new LoanException('Tidak ada denda yang perlu dilunasi.');
            }

            $locked->update(['fine_paid_at' => now()]);

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

            if ($locked->fine_status !== FineStatus::Unpaid) {
                throw new LoanException('Tidak ada denda yang bisa dibebaskan.');
            }

            $locked->update([
                'fine_waived_at' => now(),
                'fine_waived_by' => $admin->id,
            ]);

            $locked->user()->update(['suspend_until' => null]);

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
        $returnedAt = now();
        $lateDays = $this->clock->lateDays($loan->due_at, $returnedAt);
        $amount = $this->fines->amount($lateDays, $loan->fine_rate);

        $loan->update([
            'returned_at' => $returnedAt,
            'late_days' => $lateDays,
            'fine_amount' => $amount,
        ]);

        if ($lateDays > 0) {
            $this->applySuspension($loan->user, $lateDays);

            ActivityLog::record(
                'fine_assessed',
                'loan',
                $loan->id,
                $amount > 0
                    ? "Denda {$lateDays} hari telat = ".Rupiah::format($amount)." untuk pinjaman #{$loan->id} (anggota #{$loan->user_id})"
                    : "Keterlambatan {$lateDays} hari tanpa denda (tarif 0) untuk pinjaman #{$loan->id} (anggota #{$loan->user_id})",
                $actor,
            );
        }

        return $loan->refresh();
    }

    private function applySuspension(User $user, int $lateDays): void
    {
        $factor = (int) config('library.fine.suspend_factor');
        $cap = (int) config('library.fine.suspend_max_days');
        $days = min($lateDays * $factor, $cap);

        if ($days <= 0) {
            return;
        }

        $until = now()->addDays($days);

        // Baca ulang dengan kunci: dua pengembalian telat yang hampir bersamaan
        // tidak boleh saling menimpa dan memendekkan skorsing.
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $current = $locked->suspend_until;

        $locked->forceFill([
            'suspend_until' => $current !== null && $current->greaterThan($until) ? $current : $until,
        ])->save();
    }

    /** @throws LoanException */
    private function ensureEligible(User $user): void
    {
        $reasons = $this->blockReasons($user);

        if ($reasons !== []) {
            throw new LoanException($reasons[0]);
        }
    }
}
