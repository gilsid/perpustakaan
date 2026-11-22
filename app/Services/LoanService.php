<?php

namespace App\Services;

use App\Enums\BookCopyCondition;
use App\Enums\BookCopyStatus;
use App\Enums\HoldStatus;
use App\Enums\LoanFormat;
use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Hold;
use App\Models\Loan;
use App\Models\User;
use App\Support\LoanClosure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(
        private readonly FineService $fines,
        private readonly FinePolicy $finePolicy,
        private readonly LoanAccess $access,
        private readonly BorrowEligibility $eligibility,
        private readonly Audit $audit,
        private readonly HoldService $holds,
    ) {}

    /**
     * @throws LoanException
     */
    public function borrow(User $user, Book $book): Loan
    {
        $days = (int) config('library.loan.days');
        $rate = $this->finePolicy->ratePerDay();

        try {
            $loan = DB::transaction(function () use ($user, $book, $days, $rate): Loan {
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

        $this->audit->loanBorrowed($loan, $user);

        return $loan;
    }

    /**
     * Check-out eksemplar fisik oleh petugas. Anggota bisa memesan lewat web,
     * tetapi barang baru keluar saat petugas memindai kartu dan barcode.
     *
     * @throws LoanException
     */
    public function checkOutCopy(User $user, BookCopy $copy, User $admin): Loan
    {
        try {
            $loan = DB::transaction(function () use ($user, $copy, $admin): Loan {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $lockedCopy = BookCopy::query()->with('book')->whereKey($copy->id)->lockForUpdate()->firstOrFail();

                $heldCopy = Hold::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('copy_id', $lockedCopy->id)
                    ->where('status', HoldStatus::Ready)
                    ->first();

                if ($heldCopy === null && $lockedCopy->status === BookCopyStatus::OnHold) {
                    throw new LoanException('Eksemplar ini sedang ditahan untuk hold anggota lain.');
                }

                if ($block = $this->eligibility->forBook($lockedUser, $lockedCopy->book, LoanFormat::Physical, $lockedCopy, $heldCopy)->first()) {
                    throw new LoanException($block->message());
                }

                $loan = Loan::create([
                    'user_id' => $lockedUser->id,
                    'book_id' => $lockedCopy->book_id,
                    'format' => LoanFormat::Physical,
                    'copy_id' => $lockedCopy->id,
                    'borrowed_at' => now(),
                    'due_at' => now()->addDays((int) config('library.loan.physical_days')),
                    'fine_rate' => $this->finePolicy->ratePerDay(),
                ]);

                $lockedCopy->update(['status' => BookCopyStatus::Loan]);

                if ($heldCopy !== null) {
                    $this->holds->fulfill($heldCopy, $loan, $admin);
                }

                return $loan;
            });
        } catch (UniqueConstraintViolationException) {
            throw new LoanException('Eksemplar ini sedang dipinjam.');
        }

        $this->audit->copyCheckedOut($loan, $admin);

        return $loan->refresh();
    }

    /**
     * Mengembalikan buku dan menetapkan denda bila melewati tenggat.
     *
     * @throws LoanException
     */
    public function giveBack(User $user, Book $book): LoanClosure
    {
        $closure = DB::transaction(function () use ($user, $book): LoanClosure {
            // Kunci anggota lebih dulu (urutan sama dengan borrow) agar pengembalian dan
            // peminjaman bersamaan tidak saling mengunci terbalik.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $loan = Loan::query()
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->where('format', LoanFormat::Digital)
                ->whereNull('returned_at')
                ->lockForUpdate()
                ->first()
                ?? throw new LoanException('Tidak ada pinjaman aktif untuk buku ini.');

            return $this->closeLoan($loan);
        });

        $this->audit->loanReturned($closure->loan, $user);

        if ($closure->loan->late_days > 0) {
            $this->audit->fineAssessed($closure->loan, $user);
        }

        return $closure;
    }

    /**
     * Mengakhiri pinjaman dari panel admin. Denda tetap dihitung bila sudah lewat tenggat.
     *
     * @throws LoanException
     */
    public function endByAdmin(Loan $loan, User $admin): LoanClosure
    {
        $closure = DB::transaction(function () use ($loan): LoanClosure {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            if ($locked->returned_at !== null) {
                throw new LoanException('Pinjaman tidak ditemukan atau sudah selesai.');
            }

            return $this->closeLoan($locked);
        });

        if ($closure->loan->late_days > 0) {
            $this->audit->fineAssessed($closure->loan, $admin);
        }

        if ($closure->loan->isPhysical()) {
            $this->audit->copyCheckedIn($closure->loan, $admin);
        }

        $this->audit->loanEnded($closure->loan, $admin);

        return $closure;
    }

    /** @throws LoanException */
    public function checkInCopy(Loan $loan, User $actor, BookCopyCondition $condition): LoanClosure
    {
        $closure = DB::transaction(function () use ($loan, $condition): LoanClosure {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            if (! $locked->isPhysical() || $locked->returned_at !== null) {
                throw new LoanException('Eksemplar ini tidak sedang dipinjam.');
            }

            return $this->closeLoan($locked, $condition);
        });

        if ($closure->loan->late_days > 0) {
            $this->audit->fineAssessed($closure->loan, $actor);
        }

        if ($closure->loan->replacement_amount > 0) {
            $this->audit->replacementCharged($closure->loan, $actor);
        }

        $this->audit->copyCheckedIn($closure->loan, $actor);
        $this->audit->loanReturned($closure->loan, $actor);

        return $closure;
    }

    /**
     * @throws LoanException
     */
    public function renew(User $user, Book $book): Loan
    {
        $loan = $this->access->activeFor($user, $book)
            ?? throw new LoanException('Tidak ada pinjaman aktif untuk diperpanjang.');

        if ($loan->isPhysical()) {
            throw new LoanException('Eksemplar fisik harus diperpanjang di perpustakaan.');
        }

        if ($this->holds->hasPendingFor($book)) {
            throw new LoanException('Ada hold pada judul ini. Kembalikan buku dulu.');
        }

        $max = (int) config('library.loan.max_renewals');
        if (! $loan->can_renew) {
            throw new LoanException($max === 0 ? 'Perpanjangan tidak tersedia.' : "Pinjaman hanya bisa diperpanjang $max kali.");
        }

        $loan->update([
            'due_at' => $loan->due_at->copy()->addDays((int) config('library.loan.days')),
            'renewals' => $loan->renewals + 1,
        ]);

        $this->audit->loanRenewed($loan, $user);

        return $loan;
    }

    /** @throws LoanException */
    public function renewByStaff(Loan $loan, User $admin): Loan
    {
        if (! $loan->isPhysical() || $loan->returned_at !== null) {
            throw new LoanException('Pinjaman fisik tidak ditemukan.');
        }

        $book = $loan->book()->firstOrFail();
        if ($this->holds->hasPendingFor($book)) {
            throw new LoanException('Ada hold pada judul ini. Kembalikan eksemplar dulu.');
        }

        $max = (int) config('library.loan.max_renewals');
        if ($loan->renewals >= $max) {
            throw new LoanException($max === 0 ? 'Perpanjangan tidak tersedia.' : "Pinjaman hanya bisa diperpanjang $max kali.");
        }

        $loan->update([
            'due_at' => now()->addDays((int) config('library.loan.physical_days')),
            'renewals' => $loan->renewals + 1,
        ]);

        $this->audit->loanRenewed($loan, $admin);

        return $loan;
    }

    /** @throws LoanException */
    public function settleFine(Loan $loan, User $admin): void
    {
        $locked = DB::transaction(function () use ($loan): Loan {
            // Kunci baris agar dua admin yang menekan "Lunas" bersamaan tidak
            // sama-sama lolos dan mencatat log ganda.
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->settle($locked);

            return $locked;
        });

        $this->audit->finePaid($locked, $admin);
    }

    /**
     * Membebaskan denda sekaligus mencabut skorsing: alasan yang sah untuk membebaskan
     * denda (sakit, force majeure, kesalahan sistem) juga alasan sah membatalkan skorsing.
     *
     * @throws LoanException
     */
    public function waiveFine(Loan $loan, User $admin, string $reason): void
    {
        $locked = DB::transaction(function () use ($loan, $admin): Loan {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->waive($locked, $admin);

            return $locked;
        });

        $this->audit->fineWaived($locked, $admin, $reason);
    }

    /** @throws LoanException */
    public function settleReplacement(Loan $loan, User $admin): void
    {
        $locked = DB::transaction(function () use ($loan): Loan {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->settleReplacement($locked);

            return $locked;
        });

        $this->audit->replacementPaid($locked, $admin);
    }

    /** @throws LoanException */
    public function waiveReplacement(Loan $loan, User $admin, string $reason): void
    {
        $locked = DB::transaction(function () use ($loan, $admin): Loan {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->first()
                ?? throw new LoanException('Pinjaman tidak ditemukan.');

            $this->fines->waiveReplacement($locked, $admin);

            return $locked;
        });

        $this->audit->replacementWaived($locked, $admin, $reason);
    }

    /**
     * Menutup pinjaman: hitung denda, snapshot hasilnya, lalu terapkan skorsing.
     *
     * @throws LoanException
     */
    private function closeLoan(Loan $loan, BookCopyCondition $condition = BookCopyCondition::Good): LoanClosure
    {
        $closed = $this->fines->assess($loan, now());

        if ($closed->isPhysical() && $closed->copy_id !== null) {
            $copy = BookCopy::findOrFail($closed->copy_id);
            $copy->update(['status' => $condition->copyStatus()]);

            if ($condition === BookCopyCondition::Good) {
                $this->holds->reserveForCheckIn($copy);
            } else {
                $this->fines->chargeReplacement($closed, $copy);
            }
        }

        return new LoanClosure($closed, $closed->user()->first()?->suspend_until);
    }
}
