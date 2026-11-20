<?php

namespace App\Services;

use App\Enums\LoanFormat;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\User;
use App\Support\BorrowBlock;
use App\Support\BorrowStanding;

/**
 * Satu-satunya pemilik aturan "boleh meminjam atau tidak". Dua pintu masuk:
 * keadaan anggota saja untuk rak saya, dan keadaan anggota plus buku tertentu
 * untuk halaman buku dan penjaga di LoanService.
 */
final class BorrowEligibility
{
    public function __construct(
        private readonly LoanAccess $access,
        private readonly FineService $fines,
    ) {}

    /**
     * Alasan yang berasal dari keadaan anggota, tanpa melihat buku mana pun.
     *
     * @return list<BorrowBlock>
     */
    public function blocksForMember(User $user): array
    {
        $blocks = [];

        if (! $user->hasMemberNumber()) {
            $blocks[] = BorrowBlock::noMemberNumber();
        }
        if ($user->isMembershipExpired()) {
            $blocks[] = BorrowBlock::membershipExpired($user->membership_expires_at);
        }
        if ($user->isSuspended()) {
            $blocks[] = BorrowBlock::suspended($user->suspend_until);
        }

        $fines = $this->fines->unpaidTotal($user);
        if ($fines > 0) {
            $blocks[] = BorrowBlock::unpaidFine($fines);
        }

        $overdue = Loan::query()
            ->where('user_id', $user->id)
            ->overdue()
            ->with('book')
            ->orderBy('due_at')
            ->get();

        if ($overdue->isNotEmpty()) {
            $blocks[] = BorrowBlock::overdueLoans($overdue->take(2)->pluck('book.title')->all());
        }

        return $blocks;
    }

    public function forMember(User $user): BorrowStanding
    {
        return new BorrowStanding(
            blocks: $this->blocksForMember($user),
            activeLoans: $this->access->activeCount($user),
            maxActive: (int) config('library.loan.max_active'),
        );
    }

    /**
     * Alasan untuk buku tertentu, dengan urutan yang juga menjadi urutan
     * pesan: hal yang spesifik tentang buku ini, keadaan akun, lalu kuota.
     */
    public function forBook(User $user, Book $book, ?LoanFormat $format = null, ?BookCopy $copy = null): BorrowStanding
    {
        $standing = $this->forMember($user);
        $blocks = [];

        if ($format === LoanFormat::Physical) {
            if ($copy === null || ! $copy->isLoanable() || $copy->book_id !== $book->id) {
                $blocks[] = BorrowBlock::copyUnavailable();
            }
        } elseif (! $book->has_pdf) {
            $blocks[] = BorrowBlock::noPdf();
        }

        if ($this->access->hasOpenLoan($user, $book)) {
            $blocks[] = BorrowBlock::alreadyBorrowed();
        }

        $blocks = [...$blocks, ...$standing->blocks];

        if ($standing->activeLoans >= $standing->maxActive) {
            $blocks[] = BorrowBlock::quotaReached($standing->activeLoans, $standing->maxActive);
        }

        return new BorrowStanding($blocks, $standing->activeLoans, $standing->maxActive);
    }
}
